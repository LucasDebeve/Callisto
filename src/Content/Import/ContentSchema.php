<?php

declare(strict_types=1);

namespace App\Content\Import;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\NodeInterface;
use Symfony\Component\Config\Definition\Processor;

/**
 * Schémas des fichiers du dépôt de contenus (EC1). Le format est documenté dans docs/contenus.md.
 * La validation repose sur le composant Config de Symfony : clés inconnues refusées, types et valeurs contrôlés.
 */
final class ContentSchema
{
    private const CODE_PATTERN = '/^[a-z0-9][a-z0-9._-]{1,118}$/';

    /** @var array<string, NodeInterface> */
    private array $trees = [];

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    public function process(string $kind, array $data): array
    {
        $tree = $this->trees[$kind] ??= match ($kind) {
            'niveaux' => $this->levels(),
            'theme' => $this->theme(),
            'chapitre' => $this->chapter(),
            'cours' => $this->course(),
            'exercice' => $this->exercise(),
            'qcm' => $this->quiz(),
            default => throw new \InvalidArgumentException($kind),
        };

        return (new Processor())->process($tree, [$data]);
    }

    public static function isValidCode(string $code): bool
    {
        return 1 === preg_match(self::CODE_PATTERN, $code);
    }

    private function code(ArrayNodeDefinition $node, string $name = 'code'): void
    {
        $node->children()
            ->scalarNode($name)->isRequired()->cannotBeEmpty()
                ->validate()->ifTrue(static fn ($v) => !\is_string($v) || !self::isValidCode($v))
                ->thenInvalid('Identifiant %s invalide : minuscules, chiffres, point, tiret ou souligné.')->end()
            ->end();
    }

    private function levels(): NodeInterface
    {
        $builder = new TreeBuilder('niveaux');
        $root = $builder->getRootNode();
        $level = $root->children()->arrayNode('niveaux')->isRequired()->requiresAtLeastOneElement()->arrayPrototype();
        $this->code($level);
        $level->children()->scalarNode('titre')->isRequired()->cannotBeEmpty()->end()->end();

        return $builder->buildTree();
    }

    private function theme(): NodeInterface
    {
        $builder = new TreeBuilder('theme');
        $root = $builder->getRootNode();
        $this->code($root);
        $root->children()
            ->scalarNode('titre')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('description')->defaultNull()->end()
            ->integerNode('ordre')->defaultValue(0)->end()
        ->end();

        return $builder->buildTree();
    }

    private function chapter(): NodeInterface
    {
        $builder = new TreeBuilder('chapitre');
        $root = $builder->getRootNode();
        $this->code($root);
        $root->children()
            ->scalarNode('titre')->isRequired()->cannotBeEmpty()->end()
            ->integerNode('ordre')->defaultValue(0)->end()
        ->end();

        $item = $root->children()->arrayNode('elements')->defaultValue([])->arrayPrototype();
        $this->code($item);
        $item->children()
            ->enumNode('type')->values(['savoir', 'savoir-faire', 'competence'])->isRequired()->end()
            ->scalarNode('titre')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('description')->defaultNull()->end()
            ->integerNode('seuil')->min(1)->max(100)->defaultValue(100)->info('Pourcentage de bonnes réponses (savoirs).')->end()
            ->integerNode('questions_par_tentative')->min(1)->max(50)->defaultValue(10)->end()
            ->arrayNode('prerequis')->scalarPrototype()->end()->defaultValue([])->end()
            ->arrayNode('position')
                ->children()
                    ->floatNode('x')->isRequired()->end()
                    ->floatNode('y')->isRequired()->end()
                ->end()
            ->end()
        ->end();

        return $builder->buildTree();
    }

    private function course(): NodeInterface
    {
        $builder = new TreeBuilder('cours');
        $root = $builder->getRootNode();
        $this->code($root);
        $root->children()
            ->scalarNode('titre')->isRequired()->cannotBeEmpty()->end()
            ->integerNode('ordre')->defaultValue(0)->end()
            ->arrayNode('savoirs')->scalarPrototype()->end()->defaultValue([])->end()
        ->end();

        return $builder->buildTree();
    }

    private function exercise(): NodeInterface
    {
        $builder = new TreeBuilder('exercice');
        $root = $builder->getRootNode();
        $this->code($root);
        $root->children()
            ->scalarNode('titre')->isRequired()->cannotBeEmpty()->end()
            ->integerNode('ordre')->defaultValue(0)->end()
            ->booleanNode('corrige_publie')->defaultFalse()->end()
            ->arrayNode('elements')->scalarPrototype()->end()->defaultValue([])->end()
        ->end();

        return $builder->buildTree();
    }

    private function quiz(): NodeInterface
    {
        $builder = new TreeBuilder('qcm');
        $root = $builder->getRootNode();
        $this->code($root, 'savoir');
        $question = $root->children()->arrayNode('questions')->isRequired()->requiresAtLeastOneElement()->arrayPrototype();
        $this->code($question);
        $question->children()
            ->enumNode('type')->values(['unique', 'multiple', 'courte'])->isRequired()->end()
            ->scalarNode('enonce')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('code_source')->defaultNull()->info('Extrait de code affiché sous l\'énoncé.')->end()
            ->scalarNode('langage')->defaultValue('python')->end()
            ->arrayNode('choix')
                ->defaultValue([])
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('texte')->isRequired()->cannotBeEmpty()->end()
                        ->booleanNode('juste')->defaultFalse()->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('reponses_acceptees')->scalarPrototype()->end()->defaultValue([])->end()
            ->scalarNode('explication')->defaultNull()->end()
        ->end();

        return $builder->buildTree();
    }
}
