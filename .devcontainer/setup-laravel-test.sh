#!/bin/bash
set -e

# Create Laravel project if it doesn't exist
if [ ! -d "/home/vscode/laravel-test/vendor" ]; then
    echo "Creating new Laravel project..."
    cd /home/vscode
    composer create-project --prefer-dist laravel/laravel laravel-test
    
    cd laravel-test
    
    # Add the local package as a repository
    jq '.repositories=[{"type": "path","url": "/workspaces/laravel-scim-server"}]' ./composer.json > composer.json.tmp && mv composer.json.tmp composer.json
    
    # Require the package and laravel/tinker
    composer require arietimmerman/laravel-scim-server @dev
    composer require laravel/tinker
    
    # Set up SQLite database
    touch ./.database.sqlite
    echo "DB_CONNECTION=sqlite" >> ./.env
    echo "DB_DATABASE=/home/vscode/laravel-test/.database.sqlite" >> ./.env
    
    # Run migrations
    php artisan migrate
    
    # Create test users
    echo "User::factory()->count(100)->create();" | php artisan tinker
    
    echo "Laravel test environment setup complete!"
else
    echo "Laravel test environment already exists!"
fi