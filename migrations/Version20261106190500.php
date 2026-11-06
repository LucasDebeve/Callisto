<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * S11 : validations en ajout seul, garanties par la base elle-même.
 * UPDATE est toujours refusé ; DELETE n'est permis qu'au sein d'une transaction de purge RGPD
 * qui positionne explicitement la variable de session « callisto.purge » (voir StudentDataManager).
 */
final class Version20261106190500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Déclencheur d\'ajout seul sur progress_validation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION callisto_validation_append_only() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'UPDATE' THEN
                    RAISE EXCEPTION 'progress_validation est en ajout seul : modification interdite';
                END IF;
                IF TG_OP IN ('DELETE', 'TRUNCATE') AND coalesce(current_setting('callisto.purge', true), '') <> 'on' THEN
                    RAISE EXCEPTION 'progress_validation est en ajout seul : suppression réservée à la purge RGPD';
                END IF;
                RETURN OLD;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        $this->addSql('CREATE TRIGGER progress_validation_append_only BEFORE UPDATE OR DELETE ON progress_validation FOR EACH ROW EXECUTE FUNCTION callisto_validation_append_only()');
        $this->addSql('CREATE TRIGGER progress_validation_no_truncate BEFORE TRUNCATE ON progress_validation FOR EACH STATEMENT EXECUTE FUNCTION callisto_validation_append_only()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS progress_validation_no_truncate ON progress_validation');
        $this->addSql('DROP TRIGGER IF EXISTS progress_validation_append_only ON progress_validation');
        $this->addSql('DROP FUNCTION IF EXISTS callisto_validation_append_only()');
    }
}
