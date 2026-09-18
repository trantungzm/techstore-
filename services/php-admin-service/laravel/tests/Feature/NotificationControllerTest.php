<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    private const EXISTING_USER_ID = '11111111-1111-1111-1111-111111111111';

    private string $adminToken;
    private int $templateId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        $this->adminToken = $this->makeAdminToken();

        $this->templateId = DB::table('NotificationTemplates')->insertGetId([
            'Code' => 'order-ready',
            'Name' => 'Don hang san sang',
            'Channel' => 'System',
            'TitleTemplate' => 'Don {{orderCode}} san sang',
            'BodyTemplate' => 'Ban co the den lay hang',
            'IsActive' => 1,
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        DB::table('Users')->insert([
            'Id' => self::EXISTING_USER_ID,
            'Username' => 'tester',
        ]);
    }

    public function test_create_campaign_succeeds_with_valid_template_and_user(): void
    {
        $response = $this->postJson('/api/admin/notifications/campaigns', [
            'name' => 'Test campaign',
            'templateId' => $this->templateId,
            'audience' => 'SingleUser',
            'userId' => self::EXISTING_USER_ID,
        ], $this->authHeader());

        $response->assertStatus(201)->assertJson(['status' => 'Created']);

        $this->assertSame(1, DB::table('NotificationCampaigns')->count());
        $this->assertSame(1, DB::table('NotificationJobs')->count());

        $job = DB::table('NotificationJobs')->first();
        $this->assertSame(self::EXISTING_USER_ID, $job->UserId);
        $this->assertSame('Pending', $job->Status);
    }

    public function test_create_campaign_with_nonexistent_user_rolls_back_without_orphan_records(): void
    {
        $response = $this->postJson('/api/admin/notifications/campaigns', [
            'name' => 'Bad user campaign',
            'templateId' => $this->templateId,
            'audience' => 'SingleUser',
            'userId' => '99999999-9999-9999-9999-999999999999',
        ], $this->authHeader());

        $response->assertStatus(400);

        // The FK violation on NotificationJobs.UserId must roll back the whole
        // transaction — no dangling NotificationCampaigns row left behind.
        $this->assertSame(0, DB::table('NotificationCampaigns')->count());
        $this->assertSame(0, DB::table('NotificationJobs')->count());
    }

    public function test_create_campaign_single_user_audience_requires_user_id(): void
    {
        $response = $this->postJson('/api/admin/notifications/campaigns', [
            'name' => 'Missing user campaign',
            'templateId' => $this->templateId,
            'audience' => 'SingleUser',
        ], $this->authHeader());

        $response->assertStatus(400)
            ->assertJson(['message' => 'userId is required for SingleUser audience']);

        $this->assertSame(0, DB::table('NotificationCampaigns')->count());
        $this->assertSame(0, DB::table('NotificationJobs')->count());
    }

    private function authHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    private function makeAdminToken(): string
    {
        $secret = 'test-secret-key-for-phpunit';
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'role' => 'Admin',
            'unique_name' => 'tester',
            'exp' => time() + 3600,
        ]));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret, true));

        return "$header.$payload.$signature";
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function createSchema(): void
    {
        Schema::create('Users', function ($table) {
            $table->uuid('Id')->primary();
            $table->string('Username')->nullable();
        });

        Schema::create('NotificationTemplates', function ($table) {
            $table->increments('Id');
            $table->string('Code');
            $table->string('Name');
            $table->string('Channel')->default('System');
            $table->string('TitleTemplate');
            $table->string('BodyTemplate');
            $table->boolean('IsActive')->default(true);
            $table->timestamp('CreatedAt')->useCurrent();
            $table->timestamp('UpdatedAt')->useCurrent();
        });

        Schema::create('NotificationCampaigns', function ($table) {
            $table->increments('Id');
            $table->string('Name');
            $table->unsignedInteger('TemplateId');
            $table->text('PayloadJson')->nullable();
            $table->string('Audience')->default('SingleUser');
            $table->string('Status')->default('Created');
            $table->timestamp('CreatedAt')->useCurrent();

            $table->foreign('TemplateId')->references('Id')->on('NotificationTemplates');
        });

        Schema::create('NotificationJobs', function ($table) {
            $table->increments('Id');
            $table->unsignedInteger('CampaignId');
            $table->unsignedInteger('TemplateId');
            $table->uuid('UserId')->nullable();
            $table->string('Title');
            $table->string('Message');
            $table->text('PayloadJson')->nullable();
            $table->string('Status')->default('Pending');
            $table->unsignedInteger('RetryCount')->default(0);
            $table->timestamp('AvailableAt')->useCurrent();
            $table->timestamp('ProcessedAt')->nullable();
            $table->text('LastError')->nullable();
            $table->timestamp('CreatedAt')->useCurrent();

            $table->foreign('CampaignId')->references('Id')->on('NotificationCampaigns');
            $table->foreign('TemplateId')->references('Id')->on('NotificationTemplates');
            $table->foreign('UserId')->references('Id')->on('Users');
        });
    }
}
