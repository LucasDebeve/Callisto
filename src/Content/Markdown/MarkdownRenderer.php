<?php

declare(strict_types=1);

namespace App\Content\Markdown;

use Highlight\Highlighter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\HtmlRenderer;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Rendu Markdown des contenus (EC3, S13) :
 *  - HTML brut interdit : il est signalé comme erreur à l'import et échappé s'il passait quand même ;
 *  - code coloré côté serveur (aucun script tiers) ;
 *  - liens internes résolus et vérifiés ;
 *  - résultat final nettoyé par liste blanche.
 */
final class MarkdownRenderer
{
    private MarkdownParser $parser;
    private HtmlRenderer $renderer;
    private HtmlSanitizer $sanitizer;
    private Highlighter $highlighter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'table' => ['wrap' => ['enabled' => true, 'tag' => 'div', 'attributes' => ['class' => 'table-wrap']]],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new StrikethroughExtension());
        $this->highlighter = new Highlighter();
        $codeRenderer = $this->codeRenderer();
        $environment->addRenderer(FencedCode::class, $codeRenderer, 10);
        $environment->addRenderer(IndentedCode::class, $codeRenderer, 10);

        $this->parser = new MarkdownParser($environment);
        $this->renderer = new HtmlRenderer($environment);
        $this->sanitizer = new HtmlSanitizer(self::sanitizerConfig());
    }

    public static function sanitizerConfig(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['https'])
            ->allowRelativeMedias()
            ->forceHttpsUrls(false)
            ->withMaxInputLength(500_000);
        // allowAttribute() remplace la liste des éléments autorisés pour l'attribut : un seul appel par attribut.
        $config = $config
            ->allowAttribute('class', ['span', 'code', 'pre', 'div', 'td', 'th'])
            ->allowAttribute('id', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])
            ->allowAttribute('align', ['td', 'th'])
            ->allowAttribute('data-language', ['pre'])
            ->allowAttribute('title', ['a', 'img', 'abbr']);

        return $config->forceAttribute('a', 'rel', 'noopener noreferrer');
    }

    public function render(string $markdown, ?LinkResolver $links = null, int $headingOffset = 0): RenderResult
    {
        $document = $this->parser->parse($markdown);
        $errors = [];
        $toc = [];
        $slugger = new AsciiSlugger('fr');
        $usedIds = [];

        foreach (iterator_to_array($document->iterator(), false) as $node) {
            if ($node instanceof HtmlBlock || $node instanceof HtmlInline) {
                $errors[] = \sprintf('HTML brut interdit : « %s ».', mb_substr(trim($node->getLiteral()), 0, 60));
                $text = new Text($node->getLiteral());
                $node->replaceWith($node instanceof HtmlBlock ? $this->wrapParagraph($text) : $text);
                continue;
            }
            if ($node instanceof Link || $node instanceof Image) {
                $this->resolveLink($node, $links, $errors);
                continue;
            }
            if ($node instanceof Heading) {
                $level = min(6, $node->getLevel() + $headingOffset);
                $node->setLevel($level);
                $text = StringContainerHelper::getChildText($node);
                $base = strtolower((string) $slugger->slug($text)) ?: 'section';
                $id = $base;
                for ($i = 2; isset($usedIds[$id]); ++$i) {
                    $id = $base.'-'.$i;
                }
                $usedIds[$id] = true;
                $node->data->set('attributes/id', $id);
                if ($level <= 3) {
                    $toc[] = ['level' => $level, 'id' => $id, 'text' => $text];
                }
            }
        }

        $html = $this->sanitizer->sanitize((string) $this->renderer->renderDocument($document));

        return new RenderResult($html, $toc, $errors);
    }

    /** Rendu d'un extrait de code seul (questions de QCM). */
    public function renderCode(string $code, ?string $language): string
    {
        $doc = new Document();
        $block = new FencedCode(3, '`', 0);
        $block->setInfo($language ?? '');
        $block->setLiteral(rtrim($code)."\n");
        $doc->appendChild($block);

        return $this->sanitizer->sanitize((string) $this->renderer->renderDocument($doc));
    }

    private function wrapParagraph(Text $text): Node
    {
        $paragraph = new \League\CommonMark\Node\Block\Paragraph();
        $paragraph->appendChild($text);

        return $paragraph;
    }

    /**
     * @param list<string> $errors
     */
    private function resolveLink(Link|Image $node, ?LinkResolver $links, array &$errors): void
    {
        $url = $node->getUrl();
        $internal = InternalLink::parse($url);
        if (null !== $internal) {
            $resolved = $links?->resolve($internal);
            if (null === $resolved) {
                $errors[] = \sprintf('Lien interne invalide : « %s ».', $url);
                $resolved = '#';
            }
            $node->setUrl($resolved);

            return;
        }
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) && !preg_match('/^(https?|mailto):/i', $url)) {
            $errors[] = \sprintf('Lien non autorisé : « %s ».', $url);
            $node->setUrl('#');
        }
        if ($node instanceof Image && !str_starts_with($url, 'https://')) {
            $errors[] = \sprintf('Image « %s » : utiliser « fichier:chemin » pour une image du dépôt.', $url);
        }
    }

    private function codeRenderer(): NodeRendererInterface
    {
        $highlighter = $this->highlighter;

        return new class($highlighter) implements NodeRendererInterface {
            public function __construct(private readonly Highlighter $highlighter)
            {
            }

            public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
            {
                \assert($node instanceof FencedCode || $node instanceof IndentedCode);
                $language = $node instanceof FencedCode ? strtolower(trim(explode(' ', $node->getInfo() ?? '')[0])) : '';
                $code = rtrim($node->getLiteral(), "\n");
                $language = match ($language) {
                    'py', 'python3' => 'python',
                    'sh', 'shell', 'console' => 'bash',
                    'js' => 'javascript',
                    default => $language,
                };
                $inner = htmlspecialchars($code, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                if ('' !== $language) {
                    try {
                        $inner = $this->highlighter->highlight($language, $code)->value;
                    } catch (\DomainException) {
                        // Langage inconnu : rendu sans coloration.
                    }
                }
                $codeTag = new HtmlElement('code', ['class' => 'hljs'.('' !== $language ? ' language-'.$language : '')], $inner);

                return new HtmlElement('pre', ['class' => 'code-block', 'data-language' => '' !== $language ? $language : 'texte'], $codeTag);
            }
        };
    }
}
