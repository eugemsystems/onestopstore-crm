<?php

namespace Database\Seeders;

use App\Enums\AccountStatusEnums;
use App\Models\OrderStatus;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::withoutEvents(function () {
            //seed settings
            $settings = [
                ['key' => 'app_name', 'value' => 'One Stop Store CRM'],
                ['key' => 'app_version', 'value' => '1.0.0'],
                ['key' => 'app_logo', 'value' => 'logo.png'],
                ['key' => 'app_favicon', 'value' => 'favicon.png'],
                ['key' => 'app_timezone', 'value' => 'Africa/Harare'],
                ['key' => 'app_currency', 'value' => 'USD'],
                ['key' => 'app_currency_symbol', 'value' => '$'],
                ['key' => 'app_contact_email', 'value' => ''],
                ['key' => 'app_contact_phone', 'value' => '+263771000000'],
                ['key' => 'app_contact_address', 'value' => '123 One St, Harare, Zimbabwe'],
                ['key' => 'enable_registration', 'value' => true],
                ['key' => 'send_emails', 'value' => true],
                ['key' => 'send_sms', 'value' => false],
                ['key' => 'send_slack', 'value' => false],
                ['key' => 'send_telegram', 'value' => false],

            ];

            foreach ($settings as $setting) {
                \App\Models\Setting::updateOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
            }

            // 1. clear out cached permissions (so we can run over and over)
            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            // 2. define your permissions
            $permissions = [

                // users
                'view user',
                'create user',
                'update user',
                'delete user',

                // roles
                'view role',
                'create role',
                'update role',
                'delete role',

                // permissions
                'view permission',
                'create permission',
                'update permission',
                'delete permission',

                // profile
                'view profile',
                'update profile',
                'change password',

                //settings
                'view settings',
                'update settings',

                // orders
                'view orders',
                'create orders',
                'update orders',
                'delete orders',

                // order details
                'view order items',
                'create order items',
                'update order items',
                'delete order items',
            ];

            // 3. create permissions in the database
            foreach ($permissions as $perm) {
                Permission::firstOrCreate(['name' => $perm]);
            }

            // 4. create roles
            $roles = [
                'super-admin' => Permission::all(), // gets everything
                'admin'  => Permission::whereIn('name', [
                    'view profile', 'update profile', 'change password',
                    'view user',
                    'view order items',' view orders',
                ])->get(),
            ];

            foreach ($roles as $roleName => $perms) {
                $role = Role::firstOrCreate(['name' => $roleName]);
                $role->syncPermissions($perms);
            }

            $super_admin = User::create([
                'uuid'                     => Str::uuid(),
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'super-admin@onestopstore.co.zw',
                'phone_number' => '0773207759',
                'password' => Hash::make('strawberry14'),
                'photo_path' => 'default.png',
                'account_status' => AccountStatusEnums::active->name,
            ]);
            $super_admin->assignRole('super-admin');

            $admin = User::create([
                'uuid'                     => Str::uuid(),
                'first_name' => 'Raines',
                'last_name' => 'Admin',
                'email' => 'admin@onestopstore.co.zw',
                'phone_number' => '0773207758',
                'password' => Hash::make('strawberry14'),
                'photo_path' => 'default.png',
                'account_status' => AccountStatusEnums::active->name
            ]);
            $admin->assignRole('admin');

        });

        //pull orderstatus from api api/orderStatus to orderstatus table
        $api = new \GuzzleHttp\Client();

        $response = $api->request('GET', 'https://api.raines.africa/api/orderStatus');
        $orderStatus = json_decode($response->getBody(), true);
        foreach ($orderStatus['data'] as $status) {
            OrderStatus::create([
                'id' => $status['id'],
                'name' => $status['name'],
                'slug' => $status['slug'],
                'status' => $status['status'],
                'sequence' => $status['sequence'],
            ]);
        }

        //Artisan::call('orders:backfill');
    }
}
