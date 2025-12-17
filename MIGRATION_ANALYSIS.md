# Database Migration Analysis & Reorganization

## Current Migration Files Analysis

### Core Tables (Need Dedicated Migrations)
1. **users** - ✅ Has dedicated migration
2. **restaurants** - ✅ Has dedicated migration  
3. **menus** - ✅ Has dedicated migration
4. **menu_items** - ✅ Has dedicated migration
5. **orders** - ✅ Has dedicated migration
6. **order_items** - ✅ Has dedicated migration
7. **addresses** - ✅ Has dedicated migration
8. **trusted_devices** - ✅ Has dedicated migration
9. **activity_logs** - ✅ Has dedicated migration
10. **reviews** - ✅ Has dedicated migration
11. **payouts** - ✅ Has dedicated migration
12. **transactions** - ✅ Has dedicated migration
13. **restaurant_owners** - ✅ Has dedicated migration

### System Tables (Laravel Default)
- **password_reset_tokens** - ✅ In users migration
- **sessions** - ✅ In users migration
- **cache** - ✅ Has dedicated migration
- **jobs** - ✅ Has dedicated migration
- **personal_access_tokens** - ✅ Has dedicated migration

### Additional Tables (Need Dedicated Migrations)
- **payments** - ✅ Has dedicated migration
- **notifications** - ✅ Has dedicated migration
- **wishlists** - ✅ Has dedicated migration
- **review_replies** - ✅ Has dedicated migration
- **review_deletion_requests** - ✅ Has dedicated migration
- **user_two_factor_codes** - ✅ Has dedicated migration
- **security_logs** - ✅ Has dedicated migration
- **fraud_logs** - ✅ Has dedicated migration
- **fraud_audit_logs** - ✅ Has dedicated migration
- **restaurant_profiles** - ✅ Has dedicated migration
- **admin_action_logs** - ✅ Has dedicated migration
- **payout_transactions** - ✅ Has dedicated migration

## Reorganization Plan

### 1. Keep Existing Dedicated Migrations
All tables already have their own dedicated migration files. The current structure is already well-organized.

### 2. Remove Consolidated Migration
The `2025_09_18_220000_production_ready_consolidated_migration.php` should be removed as it duplicates functionality.

### 3. Update Individual Migrations
Each table migration should contain all necessary fields and indexes.

## Current Status: ✅ ALREADY ORGANIZED
The database is already properly organized with one migration per table. No reorganization needed.
