FROM php:8.1-alpine

# Base tools and PHP extensions
RUN apk add --no-cache git jq moreutils \
    && apk add --no-cache $PHPIZE_DEPS postgresql-dev sqlite-dev \
    && docker-php-ext-install pdo_pgsql pdo_sqlite \
    && pecl install xdebug-3.1.5 \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host = 172.19.0.1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Composer + fresh Laravel app
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer create-project --prefer-dist laravel/laravel example && cd example

WORKDIR /example

# Link local package
COPY . /laravel-scim-server
RUN jq '.repositories=[{"type": "path","url": "/laravel-scim-server"}]' ./composer.json | sponge ./composer.json

# Install package and dev helpers
RUN composer require arietimmerman/laravel-scim-server @dev && \
    composer require laravel/tinker

# SQLite config
RUN touch /example/database.sqlite && \
    echo "DB_CONNECTION=sqlite" >> /example/.env && \
    echo "DB_DATABASE=/example/database.sqlite" >> /example/.env && \
    echo "APP_URL=http://localhost:18123" >> /example/.env

# Make users.password nullable to allow SCIM-created users without passwords
RUN sed -i -E "s/\\$table->string\('password'\);/\\$table->string('password')->nullable();/g" \
    database/migrations/*create_users_table.php || true

# Groups table migration
RUN cat > /example/database/migrations/2021_01_01_000001_create_groups_table.php <<'EOM'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('displayName')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
EOM

# Group model with members() relation
RUN cat > app/Models/Group.php <<'EOM'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['displayName'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user', 'group_id', 'user_id')->withTimestamps();
    }
}
EOM

# Override SCIM config to use app's Group model
RUN mkdir -p app/SCIM && cat > app/SCIM/CustomSCIMConfig.php <<'EOM'
<?php

namespace App\SCIM;

use ArieTimmerman\Laravel\SCIMServer\SCIMConfig as BaseSCIMConfig;

class CustomSCIMConfig extends BaseSCIMConfig
{
    public function getGroupConfig()
    {
        $config = parent::getGroupConfig();
        $config['class'] = \App\Models\Group::class;
        return $config;
    }
}
EOM

# Bind CustomSCIMConfig in the container
RUN cat > app/Providers/AppServiceProvider.php <<'EOM'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use ArieTimmerman\Laravel\SCIMServer\SCIMConfig as BaseSCIMConfig;
use App\SCIM\CustomSCIMConfig;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BaseSCIMConfig::class, CustomSCIMConfig::class);
    }

    public function boot(): void
    {
        //
    }
}
EOM

# Group factory
RUN cat > database/factories/GroupFactory.php <<'EOM'
<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'displayName' => $this->faker->unique()->company(),
        ];
    }
}
EOM

# Pivot table for memberships
RUN cat > /example/database/migrations/2021_01_01_000002_create_group_user_table.php <<'EOM'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
    }
};
EOM

# Ensure User model has groups() relation (overwrite default)
RUN cat > app/Models/User.php <<'EOM'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user', 'user_id', 'group_id')->withTimestamps();
    }
}
EOM

# Seeder for demo data
RUN cat > /example/database/seeders/DemoSeeder.php <<'EOM'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Group;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory()->count(50)->create();
        $groups = Group::factory()->count(10)->create();

        foreach ($groups as $g) {
            $g->members()->sync($users->random(rand(3, 10))->pluck('id')->toArray());
        }
    }
}
EOM

# Run migrations and seed demo data
RUN php artisan migrate && php artisan db:seed --class=Database\\Seeders\\DemoSeeder

CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]

