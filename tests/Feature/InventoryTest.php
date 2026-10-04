<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_search_and_stock_filter_use_quantity_thresholds_and_global_summaries(): void
    {
        $this->actingAs(User::factory()->create());

        InventoryItem::create([
            'item_name' => 'Rice kits',
            'category' => 'Food',
            'quantity' => 5,
            'unit' => 'bags',
            'minimum_stock' => 10,
            'status' => 'In Stock',
        ]);
        InventoryItem::create([
            'item_name' => 'Water cases',
            'category' => 'Water',
            'quantity' => 20,
            'unit' => 'cases',
            'minimum_stock' => 10,
            'status' => 'Low Stock',
        ]);

        $response = $this->get(route('admin.inventory', [
            'search' => 'rice',
            'stock_status' => 'low',
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory')
            ->where('items.total', 1)
            ->where('items.data.0.item_name', 'Rice kits')
            ->where('totalInventoryItems', 2)
            ->where('lowStockCount', 1)
            ->where('categoryCount', 2));

        $this->assertSame('Low Stock', InventoryItem::where('item_name', 'Rice kits')->firstOrFail()->status);
        $this->assertSame('In Stock', InventoryItem::where('item_name', 'Water cases')->firstOrFail()->status);
    }

    public function test_create_and_update_recalculate_stock_status_from_the_threshold(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.inventory.store'), [
            'item_name' => 'First aid kits',
            'category' => 'Medical',
            'quantity' => 8,
            'unit' => 'kits',
            'minimum_stock' => 8,
        ])->assertRedirect(route('admin.inventory'));

        $item = InventoryItem::where('item_name', 'First aid kits')->firstOrFail();
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'status' => 'Low Stock']);

        $this->put(route('admin.inventory.update', $item->id), [
            'item_name' => $item->item_name,
            'category' => $item->category,
            'quantity' => 15,
            'unit' => $item->unit,
            'minimum_stock' => 8,
        ])->assertRedirect(route('admin.inventory'));

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'status' => 'In Stock']);
        $this->assertSame('In Stock', $item->fresh()->status);
    }

    public function test_invalid_edit_returns_field_validation_errors_for_the_inertia_form(): void
    {
        $this->actingAs(User::factory()->create());
        $item = InventoryItem::create([
            'item_name' => 'Blankets',
            'category' => 'Shelter',
            'quantity' => 10,
            'unit' => 'pcs',
            'minimum_stock' => 2,
            'status' => 'In Stock',
        ]);

        $response = $this->from(route('admin.inventory'))
            ->withHeader('X-Inertia', 'true')
            ->put(route('admin.inventory.update', $item->id), [
                '_method' => 'PUT',
                'id' => $item->id,
                'item_name' => 'Blankets',
                'category' => 'Shelter',
                'quantity' => -1,
                'unit' => 'pcs',
                'minimum_stock' => 2,
            ]);

        $response->assertRedirect(route('admin.inventory'))
            ->assertSessionHasErrors(['quantity']);
    }

    public function test_inventory_pagination_is_available_and_out_of_range_pages_are_corrected(): void
    {
        $this->actingAs(User::factory()->create());

        for ($index = 1; $index <= 12; $index++) {
            InventoryItem::create([
                'item_name' => "Supply {$index}",
                'category' => 'General',
                'quantity' => 10,
                'unit' => 'pcs',
                'minimum_stock' => 2,
                'status' => 'In Stock',
            ]);
        }

        $this->get(route('admin.inventory'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Inventory')
                ->where('items.last_page', 2)
                ->where('items.links.2.url', route('admin.inventory', ['page' => 2])));

        $this->get(route('admin.inventory', ['page' => 9]))
            ->assertRedirect(route('admin.inventory', ['page' => 2]));
    }
}
