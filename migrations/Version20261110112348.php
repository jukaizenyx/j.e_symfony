<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261110112348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_messages (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, subject VARCHAR(150) NOT NULL, message TEXT NOT NULL, status ENUM(\'Unread\',\'Read\') NOT NULL DEFAULT \'Unread\', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE login_tokens (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, user_id INT NOT NULL, INDEX user_id (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, message VARCHAR(255) NOT NULL, is_read TINYINT DEFAULT 0 NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, user_id INT NOT NULL, order_id INT UNSIGNED DEFAULT NULL, INDEX fk_notifications_user (user_id), INDEX fk_notifications_order (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_items (id INT UNSIGNED AUTO_INCREMENT NOT NULL, product_id INT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL, size VARCHAR(50) DEFAULT NULL, price NUMERIC(10, 2) NOT NULL, quantity INT UNSIGNED NOT NULL, order_id INT UNSIGNED NOT NULL, INDEX idx_order_items_order_id (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE orders (id INT UNSIGNED AUTO_INCREMENT NOT NULL, user_id INT UNSIGNED NOT NULL, full_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) NOT NULL, address TEXT NOT NULL, payment_method VARCHAR(50) NOT NULL, payment_proof VARCHAR(255) DEFAULT NULL, status ENUM(\'pending\',\'approved\',\'cancelled\',\'completed\') NOT NULL DEFAULT \'pending\', total NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_orders_user_id (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description TEXT NOT NULL, price NUMERIC(10, 2) NOT NULL, sizes TEXT NOT NULL, image VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, first_name VARCHAR(50) NOT NULL, middle_name VARCHAR(50) DEFAULT NULL, last_name VARCHAR(50) NOT NULL, email VARCHAR(150) NOT NULL, address VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, phone VARCHAR(20) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, profile_pic VARCHAR(255) DEFAULT NULL, is_admin TINYINT DEFAULT 0 NOT NULL, UNIQUE INDEX username (username), UNIQUE INDEX email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE login_tokens ADD CONSTRAINT login_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT fk_notifications_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT order_items_ibfk_1 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE login_tokens DROP FOREIGN KEY login_tokens_ibfk_1');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_user');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY fk_notifications_order');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_1');
        $this->addSql('DROP TABLE contact_messages');
        $this->addSql('DROP TABLE login_tokens');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE users');
    }
}
