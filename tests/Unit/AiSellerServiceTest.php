<?php

namespace Tests\Unit;

use App\Data\LlmTurnResultData;
use App\Enums\ConversationStage;
use App\Models\Furniture;
use App\Services\AiSellerService;
use App\Services\FurnitureRecommendationService;
use App\Services\PhoneValidator;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLlmClient;

class AiSellerServiceTest extends TestCase
{
    private FakeLlmClient $llmClient;

    private AiSellerService $service;

    private FurnitureRecommendationService $recommendationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->llmClient = new FakeLlmClient;
        $this->recommendationService = $this->createMock(FurnitureRecommendationService::class);
        $this->recommendationService
            ->method('recommend')
            ->willReturn(new Collection);
        $this->service = new AiSellerService(
            $this->llmClient,
            new PhoneValidator,
            $this->recommendationService,
        );
    }

    public function test_one_message_extracts_multiple_fields(): void
    {
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'wardrobe',
            'budget' => '70000',
            'roomSize' => 'спальня',
            'style' => 'modern',
        ]);

        $turn = $this->process('Нужен современный шкаф в спальню до 70 тысяч');

        $this->assertSame([
            'furniture_type' => 'wardrobe',
            'budget' => '70000',
            'room_size' => 'спальня',
            'style' => 'modern',
        ], $turn['leadDraft']);
        $this->assertSame(ConversationStage::Checkout, $turn['stage']);
        $this->assertTrue($turn['catalogSearchNeeded']);
    }

    public function test_null_values_do_not_clear_existing_fields(): void
    {
        $this->llmClient->result = $this->llmResult([
            'name' => 'Иван',
        ]);

        $turn = $this->process('Меня зовут Иван', [
            'furniture_type' => 'wardrobe',
            'budget' => '70000',
        ]);

        $this->assertSame('wardrobe', $turn['leadDraft']['furniture_type']);
        $this->assertSame('70000', $turn['leadDraft']['budget']);
        $this->assertSame('Иван', $turn['leadDraft']['name']);
        $this->assertStringContainsString(
            '"furniture_type":"wardrobe"',
            (string) $this->llmClient->systemPrompt,
        );
    }

    public function test_new_non_null_value_replaces_existing_value(): void
    {
        $this->llmClient->result = $this->llmResult([
            'budget' => '100000',
        ]);

        $turn = $this->process('Нет, бюджет до 100 тысяч', [
            'furniture_type' => 'wardrobe',
            'budget' => '70000',
        ]);

        $this->assertSame('100000', $turn['leadDraft']['budget']);
    }

    public function test_unknown_furniture_type_is_not_saved(): void
    {
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'table',
        ]);

        $turn = $this->process('Мне нужен стол');

        $this->assertArrayNotHasKey('furniture_type', $turn['leadDraft']);
        $this->assertSame(ConversationStage::New, $turn['stage']);
    }

    public function test_unknown_style_is_not_saved(): void
    {
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'sofa',
            'style' => 'baroque',
        ]);

        $turn = $this->process('Диван в стиле барокко');

        $this->assertSame('sofa', $turn['leadDraft']['furniture_type']);
        $this->assertArrayNotHasKey('style', $turn['leadDraft']);
    }

    #[DataProvider('validPhoneProvider')]
    public function test_valid_phone_is_normalized(string $phone, string $expected): void
    {
        $this->llmClient->result = $this->llmResult([
            'phone' => $phone,
        ]);

        $turn = $this->process($phone);

        $this->assertSame($expected, $turn['leadDraft']['phone']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validPhoneProvider(): array
    {
        return [
            'russian international' => ['+7 999 123-45-67', '+79991234567'],
            'russian local' => ['89991234567', '89991234567'],
            'german international' => ['+49 151 12345678', '+4915112345678'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_invalid_phone_is_not_accepted(string $phone): void
    {
        $this->llmClient->result = $this->llmResult([
            'assistantMessage' => 'Спасибо, телефон записан.',
            'phone' => $phone,
        ]);

        $turn = $this->process($phone);

        $this->assertArrayNotHasKey('phone', $turn['leadDraft']);
        $this->assertSame(
            'Пожалуйста, укажите корректный номер телефона в международном формате: от 10 до 15 цифр.',
            $turn['assistantMessage'],
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneProvider(): array
    {
        return [
            'too short' => ['123'],
            'letters' => ['abcdef'],
            'short international' => ['+12'],
        ];
    }

    public function test_stage_is_new_without_furniture_type(): void
    {
        $this->llmClient->result = $this->llmResult([
            'city' => 'Москва',
        ]);

        $turn = $this->process('Я из Москвы');

        $this->assertSame(ConversationStage::New, $turn['stage']);
    }

    public function test_stage_is_qualification_when_type_is_known(): void
    {
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'bed',
        ]);

        $turn = $this->process('Нужна кровать');

        $this->assertSame(ConversationStage::Qualification, $turn['stage']);
    }

    public function test_stage_is_checkout_when_furniture_parameters_are_collected(): void
    {
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'kitchen',
            'roomSize' => '12 м²',
            'style' => 'loft',
            'budget' => '250000',
        ]);

        $turn = $this->process('Кухня 12 м², лофт, бюджет 250 тысяч');

        $this->assertSame(ConversationStage::Checkout, $turn['stage']);
    }

    public function test_ready_to_submit_is_false_without_required_data(): void
    {
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'sofa',
            'name' => 'Иван',
        ]);

        $turn = $this->process('Я Иван, нужен диван');

        $this->assertFalse($turn['readyToSubmit']);
    }

    public function test_ready_to_submit_is_true_with_type_name_and_valid_phone(): void
    {
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'sofa',
            'name' => 'Иван',
            'phone' => '+7 999 123-45-67',
            'readyToSubmit' => false,
        ]);

        $turn = $this->process('Я Иван, нужен диван, +7 999 123-45-67');

        $this->assertTrue($turn['readyToSubmit']);
    }

    public function test_unchanged_recommendation_parameters_do_not_query_catalog_again(): void
    {
        $furniture = new Furniture;
        $furniture->setAttribute('id', 42);

        $recommendationService = $this->createMock(FurnitureRecommendationService::class);
        $recommendationService
            ->expects($this->once())
            ->method('recommend')
            ->willReturn(new Collection([$furniture]));
        $service = new AiSellerService(
            $this->llmClient,
            new PhoneValidator,
            $recommendationService,
        );
        $this->llmClient->result = $this->llmResult([
            'furnitureType' => 'wardrobe',
            'budget' => '70000',
        ]);

        $firstTurn = $service->processTurn([
            ['role' => 'user', 'content' => 'Нужен шкаф до 70 тысяч'],
        ], []);

        $this->llmClient->result = $this->llmResult([
            'timeline' => 'через месяц',
        ]);

        $secondTurn = $service->processTurn(
            [['role' => 'user', 'content' => 'Через месяц']],
            $firstTurn['leadDraft'],
            $firstTurn['recommendedFurnitureIds'],
            $firstTurn['recommendationFingerprint'],
        );

        $this->assertSame([42], $secondTurn['recommendedFurnitureIds']);
        $this->assertSame(
            $firstTurn['recommendationFingerprint'],
            $secondTurn['recommendationFingerprint'],
        );
    }

    /**
     * @param  array<string, string>  $leadDraft
     * @return array{
     *     assistantMessage: string,
     *     leadDraft: array<string, string>,
     *     stage: ConversationStage,
     *     catalogSearchNeeded: bool,
     *     readyToSubmit: bool
     * }
     */
    private function process(string $message, array $leadDraft = []): array
    {
        return $this->service->processTurn([
            ['role' => 'user', 'content' => $message],
        ], $leadDraft);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function llmResult(array $overrides = []): LlmTurnResultData
    {
        $values = [
            'assistantMessage' => 'Уточните следующий параметр.',
            'furnitureType' => null,
            'budget' => null,
            'roomSize' => null,
            'style' => null,
            'timeline' => null,
            'city' => null,
            'name' => null,
            'phone' => null,
            'catalogSearchNeeded' => false,
            'readyToSubmit' => false,
            ...$overrides,
        ];

        return new LlmTurnResultData(
            assistantMessage: $values['assistantMessage'],
            furnitureType: $values['furnitureType'],
            budget: $values['budget'],
            roomSize: $values['roomSize'],
            style: $values['style'],
            timeline: $values['timeline'],
            city: $values['city'],
            name: $values['name'],
            phone: $values['phone'],
            catalogSearchNeeded: $values['catalogSearchNeeded'],
            readyToSubmit: $values['readyToSubmit'],
        );
    }
}
