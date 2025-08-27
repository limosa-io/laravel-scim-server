FROM php:8.1-alpine

RUN apk add --no-cache git jq moreutils
RUN apk add --no-cache $PHPIZE_DEPS postgresql-dev \
    && docker-php-ext-install pdo_pgsql \
    && pecl install xdebug-3.1.5 \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host = 172.19.0.1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer create-project --prefer-dist laravel/laravel example && \
    cd example

WORKDIR /example

COPY . /laravel-scim-server
RUN jq '.repositories=[{"type": "path","url": "/laravel-scim-server"}]' ./composer.json | sponge ./composer.json

RUN composer require arietimmerman/laravel-scim-server @dev && \
    composer require laravel/tinker

RUN touch /example/database.sqlite && \
    echo "DB_CONNECTION=sqlite" >> /example/.env && \
    echo "DB_DATABASE=/example/database.sqlite" >> /example/.env && \
    echo "APP_URL=http://localhost:18123" >> /example/.env


# Add migration for groups table using heredoc
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

# Add Group model
RUN cat > app/Models/Group.php <<'EOM'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Group extends Model
{
    use HasFactory;

    protected $fillable = ['displayName'];
}
EOM

# Add Custom SCIM Config overriding Group model class
RUN mkdir -p app/SCIM && cat > app/SCIM/CustomSCIMConfig.php <<'EOM'
<?php

namespace App\SCIM;

use ArieTimmerman\Laravel\SCIMServer\SCIMConfig as BaseSCIMConfig;

class CustomSCIMConfig extends BaseSCIMConfig
{
    public function getGroupConfig()
    {
        $config = parent::getGroupConfig();
        // Force the group model to the example app's Group model
        $config['class'] = \App\Models\Group::class;
        return $config;
    }
}
EOM

# Override AppServiceProvider to register custom SCIMConfig binding
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
        // Additional boot logic if needed
    }
}
EOM

# Add Group factory
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

# Run migrations and seed demo data
RUN php artisan migrate && \
    echo "User::factory()->count(100)->create(); App\\Models\\Group::factory()->count(10)->create();" | php artisan tinker

CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]
