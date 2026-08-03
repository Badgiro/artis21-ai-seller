<?php

namespace Tests\Feature;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Models\Furniture;
use App\Services\FurnitureRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FurnitureRecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FurnitureRecommendationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FurnitureRecommendationService::class);
    }

    public function test_recommendations_are_real_furniture_models_from_database(): void
    {
        $furniture = $this->createFurniture();

        $recommendations = $this->service->recommend('wardrobe', null);

        $this->assertCount(1, $recommendations);
        $this->assertTrue($recommendations->first()->is($furniture));
        $this->assertDatabaseHas(Furniture::class, [
            'id' => $recommendations->first()->getKey(),
        ]);
    }

    public function test_recommendations_filter_by_furniture_type(): void
    {
        $wardrobe = $this->createFurniture();
        $this->createFurniture([
            'name' => 'Тестовый диван',
            'type' => FurnitureType::Sofa,
        ]);

        $recommendations = $this->service->recommend('wardrobe', null);

        $this->assertSame([$wardrobe->getKey()], $recommendations->modelKeys());
        $this->assertTrue($recommendations->every(
            fn (Furniture $item): bool => $item->type === FurnitureType::Wardrobe,
        ));
    }

    public function test_budget_prioritizes_items_within_budget(): void
    {
        $this->createFurniture(['name' => 'Шкаф 50', 'price' => '50000']);
        $this->createFurniture(['name' => 'Шкаф 60', 'price' => '60000']);
        $this->createFurniture(['name' => 'Шкаф 70', 'price' => '70000']);
        $this->createFurniture(['name' => 'Шкаф 90', 'price' => '90000']);

        $recommendations = $this->service->recommend('wardrobe', '70000');

        $this->assertCount(3, $recommendations);
        $this->assertTrue($recommendations->every(
            fn (Furniture $item): bool => (float) $item->price <= 70000,
        ));
    }

    public function test_matching_style_has_priority(): void
    {
        $this->createFurniture([
            'name' => 'Классический шкаф',
            'price' => '65000',
            'style' => FurnitureStyle::Classic,
        ]);
        $modern = $this->createFurniture([
            'name' => 'Современный шкаф',
            'price' => '55000',
            'style' => FurnitureStyle::Modern,
        ]);

        $recommendations = $this->service->recommend('wardrobe', '70000', 'modern');

        $this->assertTrue($recommendations->first()->is($modern));
    }

    public function test_recommendations_return_at_most_three_items(): void
    {
        foreach (range(1, 5) as $index) {
            $this->createFurniture([
                'name' => "Шкаф {$index}",
                'price' => (string) (40000 + ($index * 5000)),
            ]);
        }

        $recommendations = $this->service->recommend('wardrobe', null);

        $this->assertCount(3, $recommendations);
        $this->assertCount(3, $recommendations->modelKeys());
    }

    public function test_nearest_items_are_returned_when_nothing_fits_budget(): void
    {
        $nearest = $this->createFurniture([
            'name' => 'Ближайший шкаф',
            'price' => '50000',
        ]);
        $this->createFurniture([
            'name' => 'Дорогой шкаф',
            'price' => '90000',
        ]);

        $recommendations = $this->service->recommend('wardrobe', '30000');

        $this->assertTrue($recommendations->first()->is($nearest));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createFurniture(array $attributes = []): Furniture
    {
        return Furniture::query()->create([
            'name' => 'Тестовый шкаф',
            'type' => FurnitureType::Wardrobe,
            'price' => '55000',
            'room_size' => 'спальня, 180×60×220 см',
            'style' => FurnitureStyle::Modern,
            ...$attributes,
        ]);
    }
}
