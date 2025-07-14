FROM mcr.microsoft.com/devcontainers/php:8.2

# Create Laravel test project directory in vscode user home
USER vscode
RUN mkdir -p /home/vscode/laravel-test

# Expose port 8000 for Laravel
EXPOSE 8000

# Create setup script for Laravel test environment
COPY ./setup-laravel-test.sh /usr/local/bin/setup-laravel-test.sh
USER root
RUN chmod +x /usr/local/bin/setup-laravel-test.sh
USER vscode