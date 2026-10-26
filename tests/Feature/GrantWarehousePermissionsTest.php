<?php

namespace Tests\Feature;

use App\Enums\Business\BusinessPermission;
use App\Enums\Business\BusinessRole;
use App\Models\BusinessUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrantWarehousePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_adds_new_permissions_without_restoring_removed_ones(): void
    {
        $keeper = BusinessUser::factory()->warehouseKeeper()->create([
            'permissions' => [
                BusinessRole::WarehouseKeeper->value => [
                    BusinessPermission::WarehouseView->value,
                    BusinessPermission::StockReceive->value,
                ],
            ],
        ]);
        $accountant = BusinessUser::factory()->accountant()->create([
            'permissions' => [
                BusinessRole::Accountant->value => [
                    BusinessPermission::InvoiceView->value,
                ],
            ],
        ]);
        $admin = BusinessUser::factory()->admin()->create([
            'permissions' => [
                BusinessRole::Admin->value => [
                    BusinessPermission::InvoiceView->value,
                ],
            ],
        ]);
        $seller = BusinessUser::factory()->seller()->create([
            'permissions' => [
                BusinessRole::Seller->value => [
                    BusinessPermission::InvoiceView->value,
                ],
            ],
        ]);
        $owner = BusinessUser::factory()->owner()->create([
            'permissions' => [
                BusinessRole::Owner->value => [
                    BusinessPermission::InvoiceView->value,
                ],
            ],
        ]);
        $fallback = BusinessUser::factory()->warehouseKeeper()->create([
            'permissions' => [
                BusinessRole::Seller->value => [
                    BusinessPermission::InvoiceView->value,
                ],
            ],
        ]);

        $this->artisan('business:grant-warehouse-permissions')->assertSuccessful();

        $keeperPermissions = $keeper->fresh()->permissions[BusinessRole::WarehouseKeeper->value];
        $this->assertContains(BusinessPermission::StockCount->value, $keeperPermissions);
        $this->assertNotContains(BusinessPermission::StockViewValue->value, $keeperPermissions);
        $this->assertContains(BusinessPermission::StockReceive->value, $keeperPermissions);

        $accountantPermissions = $accountant->fresh()->permissions[BusinessRole::Accountant->value];
        $this->assertContains(BusinessPermission::StockViewValue->value, $accountantPermissions);
        $this->assertNotContains(BusinessPermission::StockCount->value, $accountantPermissions);
        $this->assertContains(BusinessPermission::InvoiceView->value, $accountantPermissions);

        $adminPermissions = $admin->fresh()->permissions[BusinessRole::Admin->value];
        $this->assertContains(BusinessPermission::StockCount->value, $adminPermissions);
        $this->assertContains(BusinessPermission::StockViewValue->value, $adminPermissions);
        $this->assertNotContains(BusinessPermission::StockReceive->value, $adminPermissions);

        $this->assertSame(
            [BusinessPermission::InvoiceView->value],
            $seller->fresh()->permissions[BusinessRole::Seller->value],
        );
        $this->assertSame(
            [BusinessPermission::InvoiceView->value],
            $owner->fresh()->permissions[BusinessRole::Owner->value],
        );
        $this->assertArrayNotHasKey(BusinessRole::WarehouseKeeper->value, $fallback->fresh()->permissions);

        $snapshot = $keeper->fresh()->permissions;

        $this->artisan('business:grant-warehouse-permissions')->assertSuccessful();

        $this->assertSame($snapshot, $keeper->fresh()->permissions);
        $this->assertSame($adminPermissions, $admin->fresh()->permissions[BusinessRole::Admin->value]);
    }
}
