<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add staff account status and product ownership for rubric access controls.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD is_staff TINYINT(1) DEFAULT 0 NOT NULL, ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE products ADD created_by_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_products_created_by_id ON products (created_by_id)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_created_by FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP FOREIGN KEY fk_products_created_by');
        $this->addSql('DROP INDEX idx_products_created_by_id ON products');
        $this->addSql('ALTER TABLE products DROP created_by_id');
        $this->addSql('ALTER TABLE users DROP is_staff, DROP is_active');
    }
}
