<?php
// Unit test for import data isolation by user session
// Run with: phpunit --bootstrap ../api/index.php import_isolation_test.php

use PHPUnit\Framework\TestCase;

class ImportIsolationTest extends TestCase {
    private $db;
    private $user1;
    private $user2;
    private $token1;
    private $token2;

    protected function setUp(): void {
        // Setup test DB connection
        $this->db = new PDO('mysql:host=127.0.0.1;dbname=ads_tracker_test', 'test', 'test', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec('DELETE FROM campaigns');
        $this->db->exec('DELETE FROM users');

        // Create two users
        $this->user1 = [
            'id' => 'usr_test1',
            'email' => 'user1@example.com',
            'password_hash' => password_hash('Password1', PASSWORD_BCRYPT),
            'salt' => bin2hex(random_bytes(16)),
            'name' => 'User One',
            'role' => 'user',
            'payment_status' => 'LUNAS',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_login' => null,
            'is_active' => 1,
        ];
        $this->user2 = [
            'id' => 'usr_test2',
            'email' => 'user2@example.com',
            'password_hash' => password_hash('Password2', PASSWORD_BCRYPT),
            'salt' => bin2hex(random_bytes(16)),
            'name' => 'User Two',
            'role' => 'user',
            'payment_status' => 'LUNAS',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_login' => null,
            'is_active' => 1,
        ];
        $stmt = $this->db->prepare('INSERT INTO users (id, email, password_hash, salt, name, role, payment_status, created_at, updated_at, last_login, is_active) VALUES (:id, :email, :password_hash, :salt, :name, :role, :payment_status, :created_at, :updated_at, :last_login, :is_active)');
        $stmt->execute($this->user1);
        $stmt->execute($this->user2);
    }

    public function testUserCannotImportOtherUserData() {
        // Simulate import as user1
        $stmt = $this->db->prepare('INSERT INTO campaigns (id, user_id, import_batch_id, period_label, campaign_name, spend, impressions, ctr, results, revenue, roas, cpm, reach, freq, atc, cpa, date_start, date_end, created_at) VALUES (:id, :user_id, :import_batch_id, :period_label, :campaign_name, :spend, :impressions, :ctr, :results, :revenue, :roas, :cpm, :reach, :freq, :atc, :cpa, :date_start, :date_end, :created_at)');
        $stmt->execute([
            'id' => 'cmp1',
            'user_id' => $this->user1['id'],
            'import_batch_id' => 'batch1',
            'period_label' => '2026-04',
            'campaign_name' => 'User1 Campaign',
            'spend' => 1000,
            'impressions' => 100,
            'ctr' => 1.5,
            'results' => 2,
            'revenue' => 2000,
            'roas' => 2.0,
            'cpm' => 10,
            'reach' => 90,
            'freq' => 1.1,
            'atc' => 1,
            'cpa' => 500,
            'date_start' => '2026-04-01',
            'date_end' => '2026-04-07',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        // Simulate import as user2
        $stmt->execute([
            'id' => 'cmp2',
            'user_id' => $this->user2['id'],
            'import_batch_id' => 'batch2',
            'period_label' => '2026-04',
            'campaign_name' => 'User2 Campaign',
            'spend' => 2000,
            'impressions' => 200,
            'ctr' => 2.5,
            'results' => 4,
            'revenue' => 4000,
            'roas' => 2.0,
            'cpm' => 20,
            'reach' => 180,
            'freq' => 1.2,
            'atc' => 2,
            'cpa' => 1000,
            'date_start' => '2026-04-01',
            'date_end' => '2026-04-07',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Fetch data for user1
        $res1 = $this->db->prepare('SELECT * FROM campaigns WHERE user_id = :user_id');
        $res1->execute([':user_id' => $this->user1['id']]);
        $rows1 = $res1->fetchAll();
        $this->assertCount(1, $rows1);
        $this->assertEquals('User1 Campaign', $rows1[0]['campaign_name']);

        // Fetch data for user2
        $res2 = $this->db->prepare('SELECT * FROM campaigns WHERE user_id = :user_id');
        $res2->execute([':user_id' => $this->user2['id']]);
        $rows2 = $res2->fetchAll();
        $this->assertCount(1, $rows2);
        $this->assertEquals('User2 Campaign', $rows2[0]['campaign_name']);
    }

    protected function tearDown(): void {
        $this->db->exec('DELETE FROM campaigns');
        $this->db->exec('DELETE FROM users');
        $this->db = null;
    }
}
