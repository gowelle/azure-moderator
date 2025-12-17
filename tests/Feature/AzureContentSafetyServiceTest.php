<?php

namespace Tests\Feature;

use Gowelle\AzureModerator\AzureContentSafetyServiceProvider;
use Gowelle\AzureModerator\Contracts\AzureContentSafetyServiceContract;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use Orchestra\Testbench\TestCase;

class AzureContentSafetyServiceTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            AzureContentSafetyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set Azure config - use test values
        $app['config']->set('azure-moderator', [
            'endpoint' => 'https://test-endpoint.com',
            'api_key' => 'test-api-key',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // setup database
        $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('test_reviews');
        $this->app['db']->connection()->getSchemaBuilder()->create('test_reviews', function ($table) {
            $table->id();
            $table->string('content');
            $table->float('rating');
            $table->string('status')->nullable();
            $table->string('moderation_reason')->nullable();
            $table->timestamps();
        });

        // Mock the service with specific scenarios
        $mock = Mockery::mock(AzureContentSafetyServiceContract::class);

        // Low rating scenario
        $mock->shouldReceive('moderate')
            ->withArgs(function ($content, $rating) {
                return $rating <= 2.0;
            })
            ->andReturn([
                'status' => 'flagged',
                'reason' => 'low_rating',
            ]);

        // High risk content scenario
        $mock->shouldReceive('moderate')
            ->withArgs(function ($content, $rating) {
                return str_contains(strtolower($content), 'inappropriate');
            })
            ->andReturn([
                'status' => 'flagged',
                'reason' => 'high_risk_content',
            ]);

        // Clean content scenario
        $mock->shouldReceive('moderate')
            ->withArgs(function ($content, $rating) {
                return $rating > 2.0 && ! str_contains(strtolower($content), 'inappropriate');
            })
            ->andReturn([
                'status' => 'approved',
                'reason' => null,
            ]);

        $this->app->instance(AzureContentSafetyServiceContract::class, $mock);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /** @test */
    public function it_should_moderate_content_successfully()
    {
        $service = app(AzureContentSafetyServiceContract::class);

        $review = new TestReview([
            'content' => 'Great product, highly recommend it!',
            'rating' => 4.5,
        ]);

        $response = $service->moderate($review->content, $review->rating);

        $this->assertEquals([
            'status' => 'approved',
            'reason' => null,
        ], $response);
    }

    /** @test */
    public function it_marks_review_as_approved_if_clean_and_high_rating()
    {
        $service = app(AzureContentSafetyServiceContract::class);

        $review = new TestReview([
            'content' => 'Awesome product, loved it!',
            'rating' => 4.5,
        ]);

        $response = $service->moderate($review->content, $review->rating);

        $review->status = $response['status'];
        $review->moderation_reason = $response['reason'];
        $review->save();

        $this->assertEquals('approved', $review->fresh()->status);
    }

    /** @test */
    public function it_marks_review_as_flagged_if_high_risk_or_low_rating()
    {
        $service = app(AzureContentSafetyServiceContract::class);

        // Test low rating scenario
        $lowRatingReview = new TestReview([
            'content' => 'Normal content but low rating',
            'rating' => 2.0,
        ]);

        $response = $service->moderate($lowRatingReview->content, $lowRatingReview->rating);

        $this->assertEquals([
            'status' => 'flagged',
            'reason' => 'low_rating',
        ], $response);

        $lowRatingReview->status = $response['status'];
        $lowRatingReview->moderation_reason = $response['reason'];
        $lowRatingReview->save();

        $this->assertEquals('flagged', $lowRatingReview->fresh()->status);
        $this->assertEquals('low_rating', $lowRatingReview->fresh()->moderation_reason);

        // Test high risk content scenario
        $highRiskReview = new TestReview([
            'content' => 'This content contains inappropriate language or harmful content',
            'rating' => 4.5,
        ]);

        $response = $service->moderate($highRiskReview->content, $highRiskReview->rating);

        $this->assertEquals([
            'status' => 'flagged',
            'reason' => 'high_risk_content',
        ], $response);

        $highRiskReview->status = $response['status'];
        $highRiskReview->moderation_reason = $response['reason'];
        $highRiskReview->save();

        $this->assertEquals('flagged', $highRiskReview->fresh()->status);
        $this->assertEquals('high_risk_content', $highRiskReview->fresh()->moderation_reason);
    }
}

class TestReview extends Model
{
    protected $table = 'test_reviews';

    protected $fillable = ['content', 'rating', 'status', 'moderation_reason'];

    public $timestamps = true;
}
