#!/bin/bash

# Email Queue Extension Deployment Script
# This script helps deploy and configure the CiviCRM Email Queue extension

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration variables
EXTENSION_KEY="com.yourorg.emailqueue"
EXTENSION_NAME="Email Queue System"
CIVICRM_PATH=""
EXTENSION_PATH=""
DB_HOST="localhost"
DB_NAME="emailqueue"
DB_USER=""
DB_PASS=""
DB_ROOT_USER="root"
DB_ROOT_PASS=""
BATCH_SIZE=50
RETRY_ATTEMPTS=3
CRON_FREQUENCY=5

# Functions
print_header() {
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}  CiviCRM Email Queue Extension Deployment${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        print_warning "Running as root. Consider running as web server user instead."
    fi
}

# Check prerequisites
check_prerequisites() {
    print_info "Checking prerequisites..."

    # Check if mysql command is available
    if ! command -v mysql &> /dev/null; then
        print_error "MySQL client not found. Please install mysql-client."
        exit 1
    fi

    # Check if PHP is available
    if ! command -v php &> /dev/null; then
        print_error "PHP not found. Please install PHP."
        exit 1
    fi

    # Check PHP version
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    if php -r "exit(version_compare(PHP_VERSION, '7.2', '<') ? 1 : 0);"; then
        print_error "PHP 7.2 or higher required. Current version: $PHP_VERSION"
        exit 1
    fi

    print_success "Prerequisites check passed"
}

# Get configuration from user
get_configuration() {
    print_info "Getting configuration..."

    # CiviCRM path
    while [[ -z "$CIVICRM_PATH" ]]; do
        read -p "Enter CiviCRM installation path: " CIVICRM_PATH
        if [[ ! -d "$CIVICRM_PATH" ]]; then
            print_error "CiviCRM path does not exist"
            CIVICRM_PATH=""
        fi
    done

    # Extension path
    EXTENSION_PATH="$CIVICRM_PATH/ext/$EXTENSION_KEY"

    # Database configuration
    read -p "Database host [$DB_HOST]: " input
    DB_HOST="${input:-$DB_HOST}"

    read -p "Database name [$DB_NAME]: " input
    DB_NAME="${input:-$DB_NAME}"

    while [[ -z "$DB_USER" ]]; do
        read -p "Database username: " DB_USER
    done

    while [[ -z "$DB_PASS" ]]; do
        read -s -p "Database password: " DB_PASS
        echo ""
    done

    # Optional root credentials for database creation
    read -p "MySQL root username (for database creation) [$DB_ROOT_USER]: " input
    DB_ROOT_USER="${input:-$DB_ROOT_USER}"

    if [[ -n "$DB_ROOT_USER" ]]; then
        read -s -p "MySQL root password: " DB_ROOT_PASS
        echo ""
    fi

    # Processing configuration
    read -p "Batch size [$BATCH_SIZE]: " input
    BATCH_SIZE="${input:-$BATCH_SIZE}"

    read -p "Retry attempts [$RETRY_ATTEMPTS]: " input
    RETRY_ATTEMPTS="${input:-$RETRY_ATTEMPTS}"

    read -p "Cron frequency in minutes [$CRON_FREQUENCY]: " input
    CRON_FREQUENCY="${input:-$CRON_FREQUENCY}"
}

# Create database and user
create_database() {
    print_info "Creating database and user..."

    if [[ -n "$DB_ROOT_USER" && -n "$DB_ROOT_PASS" ]]; then
        # Create database
        mysql -h "$DB_HOST" -u "$DB_ROOT_USER" -p"$DB_ROOT_PASS" -e "
            CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER IF NOT EXISTS '$DB_USER'@'$DB_HOST' IDENTIFIED BY '$DB_PASS';
            GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'$DB_HOST';
            FLUSH PRIVILEGES;
        " 2>/dev/null

        if [[ $? -eq 0 ]]; then
            print_success "Database and user created successfully"
        else
            print_error "Failed to create database and user"
            exit 1
        fi
    else
        print_warning "Skipping database creation. Please create manually:"
        echo "  CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        echo "  CREATE USER '$DB_USER'@'$DB_HOST' IDENTIFIED BY 'your_password';"
        echo "  GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'$DB_HOST';"
        echo "  FLUSH PRIVILEGES;"
    fi
}

# Test database connection
test_database_connection() {
    print_info "Testing database connection..."

    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1;" &>/dev/null

    if [[ $? -eq 0 ]]; then
        print_success "Database connection successful"
    else
        print_error "Database connection failed"
        exit 1
    fi
}

# Install extension
install_extension() {
    print_info "Installing extension..."

    # Check if extension directory exists
    if [[ ! -d "$EXTENSION_PATH" ]]; then
        print_error "Extension not found at $EXTENSION_PATH"
        print_info "Please copy the extension files to this location first"
        exit 1
    fi

    # Install via CV if available
    if command -v cv &> /dev/null; then
        print_info "Installing extension using CV..."
        cd "$CIVICRM_PATH"
        cv ext:install "$EXTENSION_KEY"

        if [[ $? -eq 0 ]]; then
            print_success "Extension installed successfully"
        else
            print_error "Extension installation failed"
            exit 1
        fi
    else
        print_warning "CV tool not found. Please install extension manually through CiviCRM admin interface"
        print_info "Go to Administer → System Settings → Extensions"
    fi
}

# Configure extension
configure_extension() {
    print_info "Configuring extension..."

    if command -v cv &> /dev/null; then
        cd "$CIVICRM_PATH"

        # Set configuration
        cv api3 Setting.create emailqueue_enabled=1
        cv api3 Setting.create emailqueue_db_host="$DB_HOST"
        cv api3 Setting.create emailqueue_db_name="$DB_NAME"
        cv api3 Setting.create emailqueue_db_user="$DB_USER"
        cv api3 Setting.create emailqueue_db_pass="$DB_PASS"
        cv api3 Setting.create emailqueue_batch_size="$BATCH_SIZE"
        cv api3 Setting.create emailqueue_retry_attempts="$RETRY_ATTEMPTS"

        # Test configuration
        cv api3 Emailqueue.testconnection host="$DB_HOST" name="$DB_NAME" user="$DB_USER" pass="$DB_PASS"

        if [[ $? -eq 0 ]]; then
            print_success "Extension configured successfully"
        else
            print_error "Extension configuration failed"
            exit 1
        fi
    else
        print_warning "Please configure extension manually through the settings page"
        print_info "Go to Mailings → Email Queue Settings"
    fi
}

# Setup cron job
setup_cron() {
    print_info "Setting up cron job..."

    CRON_COMMAND="*/$CRON_FREQUENCY * * * * $CIVICRM_PATH/bin/cron.php -u username -p password"

    print_info "Add the following to your crontab:"
    echo "  $CRON_COMMAND"
    echo ""
    print_info "Replace 'username' and 'password' with your CiviCRM credentials"
    print_info "To edit crontab, run: crontab -e"
}

# Run system tests
run_tests() {
    print_info "Running system tests..."

    if command -v cv &> /dev/null; then
        cd "$CIVICRM_PATH"

        # Test system status
        print_info "Testing system status..."
        cv api3 EmailqueueAdmin.getstatus

        # Test health check
        print_info "Running health check..."
        cv api3 EmailqueueAdmin.healthcheck

        # Test system
        print_info "Running system test..."
        cv api3 EmailqueueAdmin.testsystem

        print_success "System tests completed"
    else
        print_warning "Skipping automated tests (CV not available)"
    fi
}

# Generate configuration summary
generate_summary() {
    print_info "Deployment Summary:"
    echo ""
    echo "Extension: $EXTENSION_NAME"
    echo "Version: $(cat $EXTENSION_PATH/info.xml | grep -o '<version>[^<]*' | cut -d'>' -f2)"
    echo "Installation Path: $EXTENSION_PATH"
    echo ""
    echo "Database Configuration:"
    echo "  Host: $DB_HOST"
    echo "  Database: $DB_NAME"
    echo "  Username: $DB_USER"
    echo ""
    echo "Processing Configuration:"
    echo "  Batch Size: $BATCH_SIZE"
    echo "  Retry Attempts: $RETRY_ATTEMPTS"
    echo "  Cron Frequency: Every $CRON_FREQUENCY minutes"
    echo ""
    echo "Next Steps:"
    echo "1. Configure cron job as shown above"
    echo "2. Test email sending through CiviCRM"
    echo "3. Monitor queue at: Mailings → Email Queue Monitor"
    echo "4. Review settings at: Mailings → Email Queue Settings"
    echo ""
}

# Main deployment function
main() {
    print_header

    check_root
    check_prerequisites
    get_configuration
    create_database
    test_database_connection
    install_extension
    configure_extension
    setup_cron
    run_tests
    generate_summary

    print_success "Deployment completed successfully!"
    print_info "Please review the configuration and test the system."
}

# Handle command line arguments
case "${1:-}" in
    "help"|"-h"|"--help")
        echo "Usage: $0 [option]"
        echo ""
        echo "Options:"
        echo "  help    Show this help message"
        echo "  test    Run system tests only"
        echo "  config  Show current configuration"
        echo ""
        echo "Without options, runs full deployment process"
        exit 0
        ;;
    "test")
        print_header
        check_prerequisites
        run_tests
        exit 0
        ;;
    "config")
        print_header
        if command -v cv &> /dev/null; then
            cv api3 EmailqueueAdmin.getstatus
        else
            print_error "CV tool required for configuration display"
        fi
        exit 0
        ;;
    "")
        main
        ;;
    *)
        print_error "Unknown option: $1"
        print_info "Use '$0 help' for usage information"
        exit 1
        ;;
esac
