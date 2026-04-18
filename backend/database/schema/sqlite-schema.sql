-- SQLite Schema Dump (converted from MySQL)
-- Generated: 2026-04-18 14:26:55

CREATE TABLE "access_time_restrictions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "role_name" varchar(50) NOT NULL,
 "allowed_days" text NOT NULL,
 "start_time" time NOT NULL,
 "end_time" time NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NOT NULL
);
CREATE INDEX "access_time_restrictions_tenant_id_index" ON "access_time_restrictions" ("tenant_id");
CREATE INDEX "access_time_restrictions_tenant_id_idx" ON "access_time_restrictions" ("tenant_id");

CREATE TABLE "account_payable_categories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(100) NOT NULL,
 "color" varchar(20) DEFAULT '#6b7280',
 "description" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "account_payable_categories_tenant_id_is_active_index" ON "account_payable_categories" ("tenant_id","is_active");
CREATE INDEX "account_payable_categories_del_idx" ON "account_payable_categories" ("deleted_at");
CREATE INDEX "account_payable_categories_tenant_id_idx" ON "account_payable_categories" ("tenant_id");
CREATE INDEX "account_payable_categories_deleted_at_idx" ON "account_payable_categories" ("deleted_at");

CREATE TABLE "account_payable_installments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "account_payable_id" integer NOT NULL,
 "installment_number" int NOT NULL DEFAULT '1',
 "due_date" date NOT NULL,
 "amount" numeric NOT NULL,
 "paid_amount" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "paid_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "account_payable_installments_ap_inst_tenant_ap_idx" ON "account_payable_installments" ("tenant_id","account_payable_id");
CREATE INDEX "account_payable_installments_tenant_id_idx" ON "account_payable_installments" ("tenant_id");

CREATE TABLE "account_payable_payments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "account_payable_id" integer NOT NULL,
 "installment_id" integer DEFAULT NULL,
 "amount" numeric NOT NULL,
 "payment_date" date NOT NULL,
 "payment_method" varchar(50) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "account_payable_payments_ap_pay_tenant_ap_idx" ON "account_payable_payments" ("tenant_id","account_payable_id");
CREATE INDEX "account_payable_payments_tenant_id_idx" ON "account_payable_payments" ("tenant_id");

CREATE TABLE "account_plan_actions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "account_plan_id" integer NOT NULL,
 "assigned_to" integer DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "due_date" date DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "sort_order" int NOT NULL DEFAULT '0',
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "account_plan_actions_assigned_to_foreign" ON "account_plan_actions" ("assigned_to");
CREATE INDEX "account_plan_actions_apa_plan_status_idx" ON "account_plan_actions" ("account_plan_id","status");

CREATE TABLE "account_plans" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "owner_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "objective" text,
 "status" varchar(255) NOT NULL DEFAULT 'active',
 "start_date" date DEFAULT NULL,
 "target_date" date DEFAULT NULL,
 "revenue_target" numeric DEFAULT NULL,
 "revenue_current" numeric DEFAULT NULL,
 "progress_percent" int NOT NULL DEFAULT '0',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "account_plans_customer_id_foreign" ON "account_plans" ("customer_id");
CREATE INDEX "account_plans_owner_id_foreign" ON "account_plans" ("owner_id");
CREATE INDEX "account_plans_ap_tenant_cust_idx" ON "account_plans" ("tenant_id","customer_id");
CREATE INDEX "account_plans_ap_tenant_status_idx" ON "account_plans" ("tenant_id","status");
CREATE INDEX "account_plans_tenant_id_idx" ON "account_plans" ("tenant_id");

CREATE TABLE "account_receivable_categories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "account_receivable_categories_tid_slug_uq" ON "account_receivable_categories" ("tenant_id","slug");
CREATE INDEX "account_receivable_categories_tid_idx" ON "account_receivable_categories" ("tenant_id");
CREATE INDEX "account_receivable_categories_del_idx" ON "account_receivable_categories" ("deleted_at");
CREATE INDEX "account_receivable_categories_deleted_at_idx" ON "account_receivable_categories" ("deleted_at");

CREATE TABLE "account_receivable_installments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "account_receivable_id" integer NOT NULL,
 "installment_number" int NOT NULL DEFAULT '1',
 "due_date" date NOT NULL,
 "amount" numeric NOT NULL,
 "paid_amount" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "psp_external_id" varchar(255) DEFAULT NULL,
 "psp_status" varchar(30) DEFAULT NULL,
 "psp_boleto_url" text,
 "psp_boleto_barcode" varchar(255) DEFAULT NULL,
 "psp_pix_qr_code" text,
 "psp_pix_copy_paste" text,
 "paid_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "account_receivable_installments_ar_inst_tenant_ar_idx" ON "account_receivable_installments" ("tenant_id","account_receivable_id");
CREATE INDEX "account_receivable_installments_psp_external_id_index" ON "account_receivable_installments" ("psp_external_id");
CREATE INDEX "account_receivable_installments_tenant_id_idx" ON "account_receivable_installments" ("tenant_id");

CREATE TABLE "accounts_payable" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "created_by" integer DEFAULT NULL,
 "updated_by" integer DEFAULT NULL,
 "deleted_by" integer DEFAULT NULL,
 "supplier" varchar(255) DEFAULT NULL,
 "category" varchar(50) DEFAULT NULL,
 "description" varchar(255) NOT NULL,
 "amount" numeric NOT NULL,
 "amount_paid" numeric NOT NULL DEFAULT '0.00',
 "due_date" date NOT NULL,
 "paid_at" date DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "payment_method" varchar(30) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "category_id" integer DEFAULT NULL,
 "supplier_id" integer DEFAULT NULL,
 "chart_of_account_id" integer DEFAULT NULL,
 "cost_center_id" integer DEFAULT NULL,
 "penalty_amount" numeric NOT NULL DEFAULT '0.00',
 "interest_amount" numeric NOT NULL DEFAULT '0.00',
 "discount_amount" numeric NOT NULL DEFAULT '0.00',
 "work_order_id" integer DEFAULT NULL
);
CREATE INDEX "accounts_payable_tenant_id_status_index" ON "accounts_payable" ("tenant_id","status");
CREATE INDEX "accounts_payable_tenant_id_due_date_index" ON "accounts_payable" ("tenant_id","due_date");
CREATE INDEX "accounts_payable_ap_chart_account" ON "accounts_payable" ("chart_of_account_id");
CREATE INDEX "accounts_payable_ap_tenant_status_due" ON "accounts_payable" ("tenant_id","status","due_date");
CREATE INDEX "accounts_payable_created_by_foreign" ON "accounts_payable" ("created_by");
CREATE INDEX "accounts_payable_ap_deleted_at" ON "accounts_payable" ("tenant_id","deleted_at");
CREATE INDEX "accounts_payable_del_idx" ON "accounts_payable" ("deleted_at");
CREATE INDEX "accounts_payable_category_id_fk_idx" ON "accounts_payable" ("category_id");
CREATE INDEX "accounts_payable_supplier_id_fk_idx" ON "accounts_payable" ("supplier_id");
CREATE INDEX "accounts_payable_chart_of_account_id_fk_idx" ON "accounts_payable" ("chart_of_account_id");
CREATE INDEX "accounts_payable_cost_center_id_fk_idx" ON "accounts_payable" ("cost_center_id");
CREATE INDEX "accounts_payable_work_order_id_index" ON "accounts_payable" ("work_order_id");
CREATE INDEX "accounts_payable_deleted_at_idx" ON "accounts_payable" ("deleted_at");
CREATE INDEX "accounts_payable_tenant_id_idx" ON "accounts_payable" ("tenant_id");
CREATE INDEX "accounts_payable_updated_by_foreign" ON "accounts_payable" ("updated_by");
CREATE INDEX "accounts_payable_deleted_by_foreign" ON "accounts_payable" ("deleted_by");

CREATE TABLE "accounts_receivable" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "quote_id" integer DEFAULT NULL,
 "origin_type" varchar(30) DEFAULT NULL,
 "invoice_id" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "updated_by" integer DEFAULT NULL,
 "deleted_by" integer DEFAULT NULL,
 "description" varchar(255) NOT NULL,
 "amount" numeric NOT NULL,
 "amount_paid" numeric NOT NULL DEFAULT '0.00',
 "due_date" date NOT NULL,
 "paid_at" date DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "payment_method" varchar(30) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "chart_of_account_id" integer DEFAULT NULL,
 "collection_rule_id" integer DEFAULT NULL,
 "last_collection_action_at" datetime NULL DEFAULT NULL,
 "days_overdue" int NOT NULL DEFAULT '0',
 "nosso_numero" varchar(30) DEFAULT NULL,
 "numero_documento" varchar(30) DEFAULT NULL,
 "penalty_amount" numeric NOT NULL DEFAULT '0.00',
 "interest_amount" numeric NOT NULL DEFAULT '0.00',
 "discount_amount" numeric NOT NULL DEFAULT '0.00',
 "cost_center_id" integer DEFAULT NULL,
 "reference_id" integer DEFAULT NULL
);
CREATE INDEX "accounts_receivable_customer_id_foreign" ON "accounts_receivable" ("customer_id");
CREATE INDEX "accounts_receivable_tenant_id_status_index" ON "accounts_receivable" ("tenant_id","status");
CREATE INDEX "accounts_receivable_tenant_id_due_date_index" ON "accounts_receivable" ("tenant_id","due_date");
CREATE INDEX "accounts_receivable_tenant_id_customer_id_index" ON "accounts_receivable" ("tenant_id","customer_id");
CREATE INDEX "accounts_receivable_collection_rule_id_foreign" ON "accounts_receivable" ("collection_rule_id");
CREATE INDEX "accounts_receivable_ar_cnab_match_idx" ON "accounts_receivable" ("tenant_id","nosso_numero");
CREATE INDEX "accounts_receivable_ar_cnab_doc_idx" ON "accounts_receivable" ("tenant_id","numero_documento");
CREATE INDEX "accounts_receivable_ar_invoice_id_idx" ON "accounts_receivable" ("invoice_id");
CREATE INDEX "accounts_receivable_ar_work_order" ON "accounts_receivable" ("work_order_id");
CREATE INDEX "accounts_receivable_ar_tenant_paid" ON "accounts_receivable" ("tenant_id","paid_at");
CREATE INDEX "accounts_receivable_ar_tenant_status_due" ON "accounts_receivable" ("tenant_id","status","due_date");
CREATE INDEX "accounts_receivable_created_by_foreign" ON "accounts_receivable" ("created_by");
CREATE INDEX "accounts_receivable_ar_deleted_at" ON "accounts_receivable" ("tenant_id","deleted_at");
CREATE INDEX "accounts_receivable_del_idx" ON "accounts_receivable" ("deleted_at");
CREATE INDEX "accounts_receivable_work_order_id_fk_idx" ON "accounts_receivable" ("work_order_id");
CREATE INDEX "accounts_receivable_chart_of_account_id_fk_idx" ON "accounts_receivable" ("chart_of_account_id");
CREATE INDEX "accounts_receivable_ar_quote_id_idx" ON "accounts_receivable" ("quote_id");
CREATE INDEX "accounts_receivable_ar_origin_type_idx" ON "accounts_receivable" ("origin_type");
CREATE INDEX "accounts_receivable_cost_center_id_foreign" ON "accounts_receivable" ("cost_center_id");
CREATE INDEX "accounts_receivable_tenant_id_idx" ON "accounts_receivable" ("tenant_id");
CREATE INDEX "accounts_receivable_deleted_at_idx" ON "accounts_receivable" ("deleted_at");
CREATE INDEX "accounts_receivable_updated_by_foreign" ON "accounts_receivable" ("updated_by");
CREATE INDEX "accounts_receivable_deleted_by_foreign" ON "accounts_receivable" ("deleted_by");

CREATE TABLE "accreditation_scopes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "accreditation_number" varchar(100) NOT NULL,
 "accrediting_body" varchar(100) NOT NULL DEFAULT 'Cgcre/Inmetro',
 "scope_description" text NOT NULL,
 "equipment_categories" text NOT NULL,
 "valid_from" date NOT NULL,
 "valid_until" date NOT NULL,
 "certificate_file" varchar(500) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "accreditation_scopes_tenant_id_is_active_index" ON "accreditation_scopes" ("tenant_id","is_active");

CREATE TABLE "admissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "candidate_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'candidate_approved',
 "start_date" date DEFAULT NULL,
 "salary" numeric DEFAULT NULL,
 "salary_confirmed" tinyint NOT NULL DEFAULT '0',
 "documents_completed" tinyint NOT NULL DEFAULT '0',
 "aso_result" varchar(255) DEFAULT NULL,
 "aso_date" date DEFAULT NULL,
 "esocial_receipt" varchar(255) DEFAULT NULL,
 "email_provisioned" tinyint NOT NULL DEFAULT '0',
 "role_assigned" tinyint NOT NULL DEFAULT '0',
 "mandatory_trainings_completed" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "admissions_tenant_id_index" ON "admissions" ("tenant_id");
CREATE INDEX "admissions_candidate_id_index" ON "admissions" ("candidate_id");
CREATE INDEX "admissions_user_id_index" ON "admissions" ("user_id");
CREATE INDEX "admissions_tenant_id_idx" ON "admissions" ("tenant_id");

CREATE TABLE "alert_configurations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "alert_type" varchar(255) NOT NULL,
 "is_enabled" tinyint NOT NULL DEFAULT '1',
 "channels" text DEFAULT NULL,
 "days_before" int DEFAULT NULL,
 "cron_expression" varchar(255) DEFAULT NULL,
 "recipients" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "escalation_hours" tinyint DEFAULT NULL,
 "escalation_recipients" text DEFAULT NULL,
 "blackout_start" varchar(5) DEFAULT NULL,
 "blackout_end" varchar(5) DEFAULT NULL,
 "threshold_amount" numeric DEFAULT NULL
);
CREATE UNIQUE INDEX "alert_configurations_tenant_id_alert_type_unique" ON "alert_configurations" ("tenant_id","alert_type");

CREATE TABLE "analytics_datasets" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "source_modules" text NOT NULL,
 "query_definition" text NOT NULL,
 "refresh_strategy" varchar(20) NOT NULL DEFAULT 'manual',
 "cache_ttl_minutes" int NOT NULL DEFAULT '1440',
 "last_refreshed_at" datetime NULL DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "analytics_datasets_created_by_foreign" ON "analytics_datasets" ("created_by");
CREATE INDEX "analytics_datasets_tenant_active_idx" ON "analytics_datasets" ("tenant_id","is_active");
CREATE INDEX "analytics_datasets_tenant_id_idx" ON "analytics_datasets" ("tenant_id");

CREATE TABLE "api_keys" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "key_hash" varchar(64) NOT NULL,
 "prefix" varchar(16) NOT NULL,
 "permissions" text NOT NULL,
 "expires_at" date DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_by" integer NOT NULL,
 "last_used_at" datetime NULL DEFAULT NULL,
 "revoked_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "api_keys_key_hash_index" ON "api_keys" ("key_hash");
CREATE INDEX "api_keys_ak_tenant_active" ON "api_keys" ("tenant_id","is_active");
CREATE INDEX "api_keys_ak_created_by" ON "api_keys" ("created_by");
CREATE INDEX "api_keys_tid_idx" ON "api_keys" ("tenant_id");
CREATE INDEX "api_keys_tenant_id_idx" ON "api_keys" ("tenant_id");

CREATE TABLE "asset_disposals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "asset_record_id" integer NOT NULL,
 "disposal_date" date NOT NULL,
 "reason" varchar(20) NOT NULL,
 "disposal_value" numeric DEFAULT NULL,
 "book_value_at_disposal" numeric NOT NULL,
 "gain_loss" numeric NOT NULL,
 "fiscal_note_id" integer DEFAULT NULL,
 "notes" text,
 "approved_by" integer NOT NULL,
 "created_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "asset_disposals_asset_record_id_foreign" ON "asset_disposals" ("asset_record_id");
CREATE INDEX "asset_disposals_fiscal_note_id_foreign" ON "asset_disposals" ("fiscal_note_id");
CREATE INDEX "asset_disposals_approved_by_foreign" ON "asset_disposals" ("approved_by");
CREATE INDEX "asset_disposals_created_by_foreign" ON "asset_disposals" ("created_by");
CREATE INDEX "asset_disposals_tenant_date_idx" ON "asset_disposals" ("tenant_id","disposal_date");
CREATE INDEX "asset_disposals_tenant_id_idx" ON "asset_disposals" ("tenant_id");

CREATE TABLE "asset_inventories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "asset_record_id" integer NOT NULL,
 "inventory_date" date NOT NULL,
 "counted_location" varchar(255) DEFAULT NULL,
 "counted_status" varchar(30) DEFAULT NULL,
 "condition_ok" tinyint NOT NULL DEFAULT '1',
 "divergent" tinyint NOT NULL DEFAULT '0',
 "offline_reference" varchar(100) DEFAULT NULL,
 "synced_from_pwa" tinyint NOT NULL DEFAULT '0',
 "notes" text,
 "counted_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "asset_inventories_asset_record_id_foreign" ON "asset_inventories" ("asset_record_id");
CREATE INDEX "asset_inventories_counted_by_foreign" ON "asset_inventories" ("counted_by");
CREATE INDEX "asset_inventories_tenant_asset_idx" ON "asset_inventories" ("tenant_id","asset_record_id");
CREATE INDEX "asset_inventories_tenant_date_idx" ON "asset_inventories" ("tenant_id","inventory_date");
CREATE INDEX "asset_inventories_tenant_id_idx" ON "asset_inventories" ("tenant_id");

CREATE TABLE "asset_movements" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "asset_record_id" integer NOT NULL,
 "movement_type" varchar(30) NOT NULL,
 "from_location" varchar(255) DEFAULT NULL,
 "to_location" varchar(255) DEFAULT NULL,
 "from_responsible_user_id" integer DEFAULT NULL,
 "to_responsible_user_id" integer DEFAULT NULL,
 "moved_at" datetime NOT NULL,
 "notes" text,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "asset_movements_asset_record_id_foreign" ON "asset_movements" ("asset_record_id");
CREATE INDEX "asset_movements_from_responsible_user_id_foreign" ON "asset_movements" ("from_responsible_user_id");
CREATE INDEX "asset_movements_to_responsible_user_id_foreign" ON "asset_movements" ("to_responsible_user_id");
CREATE INDEX "asset_movements_created_by_foreign" ON "asset_movements" ("created_by");
CREATE INDEX "asset_movements_tenant_asset_idx" ON "asset_movements" ("tenant_id","asset_record_id");
CREATE INDEX "asset_movements_tenant_type_idx" ON "asset_movements" ("tenant_id","movement_type");
CREATE INDEX "asset_movements_tenant_id_idx" ON "asset_movements" ("tenant_id");

CREATE TABLE "asset_records" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "code" varchar(50) NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "category" varchar(50) NOT NULL,
 "acquisition_date" date NOT NULL,
 "acquisition_value" numeric NOT NULL,
 "residual_value" numeric NOT NULL DEFAULT '0.00',
 "useful_life_months" int NOT NULL,
 "depreciation_method" varchar(30) NOT NULL,
 "depreciation_rate" numeric NOT NULL DEFAULT '0.0000',
 "accumulated_depreciation" numeric NOT NULL DEFAULT '0.00',
 "current_book_value" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(30) NOT NULL DEFAULT 'active',
 "location" varchar(255) DEFAULT NULL,
 "responsible_user_id" integer DEFAULT NULL,
 "nf_number" varchar(50) DEFAULT NULL,
 "nf_serie" varchar(10) DEFAULT NULL,
 "supplier_id" integer DEFAULT NULL,
 "fleet_vehicle_id" integer DEFAULT NULL,
 "ciap_credit_type" varchar(20) DEFAULT NULL,
 "ciap_total_installments" int DEFAULT NULL,
 "ciap_installments_taken" int DEFAULT '0',
 "last_depreciation_at" date DEFAULT NULL,
 "disposed_at" date DEFAULT NULL,
 "disposal_reason" varchar(20) DEFAULT NULL,
 "disposal_value" numeric DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "crm_deal_id" integer DEFAULT NULL
);
CREATE UNIQUE INDEX "asset_records_tenant_code_unique" ON "asset_records" ("tenant_id","code");
CREATE INDEX "asset_records_responsible_user_id_foreign" ON "asset_records" ("responsible_user_id");
CREATE INDEX "asset_records_supplier_id_foreign" ON "asset_records" ("supplier_id");
CREATE INDEX "asset_records_fleet_vehicle_id_foreign" ON "asset_records" ("fleet_vehicle_id");
CREATE INDEX "asset_records_created_by_foreign" ON "asset_records" ("created_by");
CREATE INDEX "asset_records_tenant_category_idx" ON "asset_records" ("tenant_id","category");
CREATE INDEX "asset_records_tenant_status_idx" ON "asset_records" ("tenant_id","status");
CREATE INDEX "asset_records_crm_deal_id_foreign" ON "asset_records" ("crm_deal_id");
CREATE INDEX "asset_records_deleted_at_idx" ON "asset_records" ("deleted_at");

CREATE TABLE "asset_tag_scans" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "asset_tag_id" integer NOT NULL,
 "scanned_by" integer NOT NULL,
 "action" varchar(50) NOT NULL DEFAULT 'scan',
 "location" varchar(255) DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "asset_tag_scans_asset_tag_id_foreign" ON "asset_tag_scans" ("asset_tag_id");
CREATE INDEX "asset_tag_scans_tenant_id_idx" ON "asset_tag_scans" ("tenant_id");

CREATE TABLE "asset_tags" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "tag_code" varchar(100) NOT NULL,
 "tag_type" varchar NOT NULL DEFAULT 'qrcode',
 "taggable_type" varchar(255) NOT NULL,
 "taggable_id" integer NOT NULL,
 "status" varchar NOT NULL DEFAULT 'active',
 "location" varchar(255) DEFAULT NULL,
 "last_scanned_at" datetime NULL DEFAULT NULL,
 "last_scanned_by" integer DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "asset_tags_tag_code_unique" ON "asset_tags" ("tag_code");
CREATE INDEX "asset_tags_taggable_type_taggable_id_index" ON "asset_tags" ("taggable_type","taggable_id");
CREATE INDEX "asset_tags_tenant_id_index" ON "asset_tags" ("tenant_id");
CREATE INDEX "asset_tags_tenant_id_idx" ON "asset_tags" ("tenant_id");

CREATE TABLE "audit_blockchain_hashes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "table_name" varchar(255) NOT NULL,
 "record_id" integer NOT NULL,
 "sha256_hash" varchar(64) NOT NULL,
 "previous_hash" varchar(64) DEFAULT NULL,
 "user_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "audit_blockchain_hashes_user_id_foreign" ON "audit_blockchain_hashes" ("user_id");
CREATE INDEX "audit_blockchain_hashes_table_name_record_id_index" ON "audit_blockchain_hashes" ("table_name","record_id");
CREATE INDEX "audit_blockchain_hashes_tid_idx" ON "audit_blockchain_hashes" ("tenant_id");
CREATE INDEX "audit_blockchain_hashes_tenant_id_idx" ON "audit_blockchain_hashes" ("tenant_id");

CREATE TABLE "audit_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "user_id" integer DEFAULT NULL,
 "action" varchar(50) NOT NULL,
 "auditable_type" varchar(255) DEFAULT NULL,
 "auditable_id" integer DEFAULT NULL,
 "description" varchar(255) DEFAULT NULL,
 "old_values" text DEFAULT NULL,
 "new_values" text DEFAULT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "user_agent" varchar(255) DEFAULT NULL,
 "created_at" datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX "audit_logs_tenant_id_created_at_index" ON "audit_logs" ("tenant_id","created_at");
CREATE INDEX "audit_logs_tenant_id_auditable_type_auditable_id_index" ON "audit_logs" ("tenant_id","auditable_type","auditable_id");
CREATE INDEX "audit_logs_audit_auditable_poly" ON "audit_logs" ("auditable_type","auditable_id");
CREATE INDEX "audit_logs_audit_user" ON "audit_logs" ("user_id");
CREATE INDEX "audit_logs_tenant_id_idx" ON "audit_logs" ("tenant_id");

CREATE TABLE "auto_assignment_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "entity_type" varchar(255) NOT NULL DEFAULT 'work_order',
 "strategy" varchar(255) NOT NULL DEFAULT 'round_robin',
 "conditions" text DEFAULT NULL,
 "technician_ids" text DEFAULT NULL,
 "required_skills" text DEFAULT NULL,
 "priority" int NOT NULL DEFAULT '10',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "deleted_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "auto_assignment_rules_tenant_id_entity_type_is_active_index" ON "auto_assignment_rules" ("tenant_id","entity_type","is_active");
CREATE INDEX "auto_assignment_rules_del_idx" ON "auto_assignment_rules" ("deleted_at");
CREATE INDEX "auto_assignment_rules_tenant_id_idx" ON "auto_assignment_rules" ("tenant_id");
CREATE INDEX "auto_assignment_rules_deleted_at_idx" ON "auto_assignment_rules" ("deleted_at");

CREATE TABLE "auto_purchase_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "min_stock" int NOT NULL DEFAULT '0',
 "reorder_quantity" int NOT NULL DEFAULT '1',
 "preferred_supplier_id" integer DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "last_triggered_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "auto_purchase_rules_tenant_id_product_id_unique" ON "auto_purchase_rules" ("tenant_id","product_id");
CREATE INDEX "auto_purchase_rules_product_id_foreign" ON "auto_purchase_rules" ("product_id");
CREATE INDEX "auto_purchase_rules_preferred_supplier_id_foreign" ON "auto_purchase_rules" ("preferred_supplier_id");

CREATE TABLE "automation_report_formats" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "automation_report_formats_tid_slug_uq" ON "automation_report_formats" ("tenant_id","slug");
CREATE INDEX "automation_report_formats_tid_idx" ON "automation_report_formats" ("tenant_id");
CREATE INDEX "automation_report_formats_del_idx" ON "automation_report_formats" ("deleted_at");
CREATE INDEX "automation_report_formats_deleted_at_idx" ON "automation_report_formats" ("deleted_at");

CREATE TABLE "automation_report_frequencies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "automation_report_frequencies_tid_slug_uq" ON "automation_report_frequencies" ("tenant_id","slug");
CREATE INDEX "automation_report_frequencies_tid_idx" ON "automation_report_frequencies" ("tenant_id");
CREATE INDEX "automation_report_frequencies_del_idx" ON "automation_report_frequencies" ("deleted_at");
CREATE INDEX "automation_report_frequencies_deleted_at_idx" ON "automation_report_frequencies" ("deleted_at");

CREATE TABLE "automation_report_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "automation_report_types_tid_slug_uq" ON "automation_report_types" ("tenant_id","slug");
CREATE INDEX "automation_report_types_tid_idx" ON "automation_report_types" ("tenant_id");
CREATE INDEX "automation_report_types_del_idx" ON "automation_report_types" ("deleted_at");
CREATE INDEX "automation_report_types_deleted_at_idx" ON "automation_report_types" ("deleted_at");

CREATE TABLE "automation_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "trigger_event" varchar(100) NOT NULL,
 "conditions" text DEFAULT NULL,
 "actions" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "execution_count" int NOT NULL DEFAULT '0',
 "last_executed_at" datetime NULL DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "automation_rules_created_by_foreign" ON "automation_rules" ("created_by");
CREATE INDEX "automation_rules_tid_idx" ON "automation_rules" ("tenant_id");
CREATE INDEX "automation_rules_del_idx" ON "automation_rules" ("deleted_at");
CREATE INDEX "automation_rules_tenant_id_idx" ON "automation_rules" ("tenant_id");
CREATE INDEX "automation_rules_deleted_at_idx" ON "automation_rules" ("deleted_at");

CREATE TABLE "auvo_id_mappings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "entity_type" varchar(30) NOT NULL,
 "auvo_id" integer NOT NULL,
 "local_id" integer DEFAULT NULL,
 "import_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "auvo_id_mappings_tenant_id_entity_type_auvo_id_unique" ON "auvo_id_mappings" ("tenant_id","entity_type","auvo_id");
CREATE INDEX "auvo_id_mappings_tenant_id_entity_type_local_id_index" ON "auvo_id_mappings" ("tenant_id","entity_type","local_id");
CREATE INDEX "auvo_id_mappings_import_id_index" ON "auvo_id_mappings" ("import_id");

CREATE TABLE "auvo_imports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "entity_type" varchar(30) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "total_fetched" int NOT NULL DEFAULT '0',
 "total_imported" int NOT NULL DEFAULT '0',
 "total_updated" int NOT NULL DEFAULT '0',
 "total_skipped" int NOT NULL DEFAULT '0',
 "total_errors" int NOT NULL DEFAULT '0',
 "error_log" text DEFAULT NULL,
 "imported_ids" text DEFAULT NULL,
 "duplicate_strategy" varchar(20) NOT NULL DEFAULT 'skip',
 "filters" text DEFAULT NULL,
 "started_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "last_synced_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "auvo_imports_tenant_id_entity_type_index" ON "auvo_imports" ("tenant_id","entity_type");
CREATE INDEX "auvo_imports_tenant_id_status_index" ON "auvo_imports" ("tenant_id","status");
CREATE INDEX "auvo_imports_user_id_foreign" ON "auvo_imports" ("user_id");
CREATE INDEX "auvo_imports_tenant_id_idx" ON "auvo_imports" ("tenant_id");

CREATE TABLE "auxiliary_tools" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "serial_number" varchar(255) DEFAULT NULL,
 "type" varchar(255) DEFAULT NULL,
 "calibration_due_date" date DEFAULT NULL,
 "last_calibration_date" date DEFAULT NULL,
 "certificate_number" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'active',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "auxiliary_tools_tid_idx" ON "auxiliary_tools" ("tenant_id");
CREATE INDEX "auxiliary_tools_del_idx" ON "auxiliary_tools" ("deleted_at");
CREATE INDEX "auxiliary_tools_tenant_id_idx" ON "auxiliary_tools" ("tenant_id");
CREATE INDEX "auxiliary_tools_deleted_at_idx" ON "auxiliary_tools" ("deleted_at");

CREATE TABLE "bank_account_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "bank_account_types_tid_slug_uq" ON "bank_account_types" ("tenant_id","slug");
CREATE INDEX "bank_account_types_tid_idx" ON "bank_account_types" ("tenant_id");
CREATE INDEX "bank_account_types_del_idx" ON "bank_account_types" ("deleted_at");
CREATE INDEX "bank_account_types_deleted_at_idx" ON "bank_account_types" ("deleted_at");

CREATE TABLE "bank_accounts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "bank_name" varchar(255) NOT NULL,
 "agency" varchar(255) DEFAULT NULL,
 "account_number" varchar(255) DEFAULT NULL,
 "account_type" varchar NOT NULL DEFAULT 'corrente',
 "pix_key" varchar(255) DEFAULT NULL,
 "balance" numeric NOT NULL DEFAULT '0.00',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "initial_balance" numeric DEFAULT NULL
);
CREATE INDEX "bank_accounts_tenant_id_is_active_index" ON "bank_accounts" ("tenant_id","is_active");
CREATE INDEX "bank_accounts_created_by_foreign" ON "bank_accounts" ("created_by");
CREATE INDEX "bank_accounts_ba_deleted_at" ON "bank_accounts" ("tenant_id","deleted_at");
CREATE INDEX "bank_accounts_del_idx" ON "bank_accounts" ("deleted_at");
CREATE INDEX "bank_accounts_tenant_id_idx" ON "bank_accounts" ("tenant_id");
CREATE INDEX "bank_accounts_deleted_at_idx" ON "bank_accounts" ("deleted_at");

CREATE TABLE "bank_statement_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "bank_statement_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "date" date NOT NULL,
 "description" text,
 "amount" numeric NOT NULL,
 "type" varchar(10) NOT NULL,
 "matched_type" varchar(50) DEFAULT NULL,
 "matched_id" integer DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "possible_duplicate" tinyint NOT NULL DEFAULT '0',
 "category" varchar(255) DEFAULT NULL,
 "reconciled_by" varchar DEFAULT NULL,
 "reconciled_at" datetime NULL DEFAULT NULL,
 "reconciled_by_user_id" integer DEFAULT NULL,
 "rule_id" integer DEFAULT NULL,
 "transaction_id" varchar(100) DEFAULT NULL
);
CREATE INDEX "bank_statement_entries_reconciled_by_user_id_foreign" ON "bank_statement_entries" ("reconciled_by_user_id");
CREATE INDEX "bank_statement_entries_rule_id_foreign" ON "bank_statement_entries" ("rule_id");
CREATE INDEX "bank_statement_entries_transaction_id_index" ON "bank_statement_entries" ("transaction_id");
CREATE INDEX "bank_statement_entries_bse_statement" ON "bank_statement_entries" ("bank_statement_id");
CREATE INDEX "bank_statement_entries_bse_tenant_status" ON "bank_statement_entries" ("tenant_id","status");
CREATE INDEX "bank_statement_entries_tid_idx" ON "bank_statement_entries" ("tenant_id");
CREATE INDEX "bank_statement_entries_matched_id_index" ON "bank_statement_entries" ("matched_id");
CREATE INDEX "bank_statement_entries_tenant_id_idx" ON "bank_statement_entries" ("tenant_id");

CREATE TABLE "bank_statements" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "filename" varchar(255) NOT NULL,
 "imported_at" datetime NULL DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "total_entries" int NOT NULL DEFAULT '0',
 "matched_entries" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "format" varchar(20) NOT NULL DEFAULT 'ofx',
 "bank_account_id" integer DEFAULT NULL
);
CREATE INDEX "bank_statements_created_by_foreign" ON "bank_statements" ("created_by");
CREATE INDEX "bank_statements_bank_account_id_foreign" ON "bank_statements" ("bank_account_id");
CREATE INDEX "bank_statements_tid_idx" ON "bank_statements" ("tenant_id");
CREATE INDEX "bank_statements_tenant_id_idx" ON "bank_statements" ("tenant_id");

CREATE TABLE "batches" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "code" varchar(255) NOT NULL,
 "expires_at" date DEFAULT NULL,
 "cost_price" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "batches_product_id_foreign" ON "batches" ("product_id");
CREATE INDEX "batches_tenant_id_product_id_code_index" ON "batches" ("tenant_id","product_id","code");
CREATE INDEX "batches_del_idx" ON "batches" ("deleted_at");
CREATE INDEX "batches_tenant_id_idx" ON "batches" ("tenant_id");
CREATE INDEX "batches_deleted_at_idx" ON "batches" ("deleted_at");

CREATE TABLE "biometric_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "enabled" tinyint NOT NULL DEFAULT '0',
 "type" varchar(20) DEFAULT NULL,
 "device_id" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "biometric_configs_user_id_unique" ON "biometric_configs" ("user_id");
CREATE INDEX "biometric_configs_device_id_index" ON "biometric_configs" ("device_id");
CREATE INDEX "biometric_configs_tenant_id_idx" ON "biometric_configs" ("tenant_id");

CREATE TABLE "biometric_consents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "data_type" varchar(255) NOT NULL,
 "legal_basis" varchar(255) NOT NULL,
 "purpose" text NOT NULL,
 "consented_at" date NOT NULL,
 "expires_at" date DEFAULT NULL,
 "revoked_at" date DEFAULT NULL,
 "alternative_method" varchar(255) DEFAULT NULL,
 "retention_days" int NOT NULL DEFAULT '365',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "biometric_consents_user_id_foreign" ON "biometric_consents" ("user_id");
CREATE INDEX "biometric_consents_tenant_id_user_id_data_type_index" ON "biometric_consents" ("tenant_id","user_id","data_type");

CREATE TABLE "branches" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "code" varchar(20) DEFAULT NULL,
 "address_street" varchar(255) DEFAULT NULL,
 "address_number" varchar(20) DEFAULT NULL,
 "address_complement" varchar(100) DEFAULT NULL,
 "address_neighborhood" varchar(100) DEFAULT NULL,
 "address_city" varchar(100) DEFAULT NULL,
 "address_state" varchar(2) DEFAULT NULL,
 "address_zip" varchar(10) DEFAULT NULL,
 "phone" varchar(20) DEFAULT NULL,
 "email" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "branches_tenant_code_index" ON "branches" ("tenant_id","code");
CREATE INDEX "branches_tenant_id_idx" ON "branches" ("tenant_id");

CREATE TABLE "business_hours" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "day_of_week" tinyint NOT NULL,
 "start_time" time NOT NULL,
 "end_time" time NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "business_hours_tenant_id_day_of_week_unique" ON "business_hours" ("tenant_id","day_of_week");
CREATE INDEX "business_hours_tenant_id_index" ON "business_hours" ("tenant_id");

CREATE TABLE "cache" (
 "key" varchar(255) NOT NULL,
 "value" text NOT NULL,
 "expiration" int NOT NULL,
 PRIMARY KEY ("key")
);
CREATE INDEX "cache_expiration_index" ON "cache" ("expiration");

CREATE TABLE "cache_locks" (
 "key" varchar(255) NOT NULL,
 "owner" varchar(255) NOT NULL,
 "expiration" int NOT NULL,
 PRIMARY KEY ("key")
);
CREATE INDEX "cache_locks_expiration_index" ON "cache_locks" ("expiration");

CREATE TABLE "calibration_decision_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_calibration_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "decision_rule" varchar(20) NOT NULL,
 "inputs" text NOT NULL,
 "outputs" text NOT NULL,
 "engine_version" varchar(20) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "calibration_decision_logs_equipment_calibration_id_foreign" ON "calibration_decision_logs" ("equipment_calibration_id");
CREATE INDEX "calibration_decision_logs_user_id_foreign" ON "calibration_decision_logs" ("user_id");
CREATE INDEX "calibration_decision_logs_cal_decision_logs_tenant_cal_idx" ON "calibration_decision_logs" ("tenant_id","equipment_calibration_id");

CREATE TABLE "calibration_readings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_calibration_id" integer NOT NULL,
 "reference_value" numeric NOT NULL,
 "indication_increasing" numeric DEFAULT NULL,
 "indication_decreasing" numeric DEFAULT NULL,
 "error" numeric DEFAULT NULL,
 "expanded_uncertainty" numeric DEFAULT NULL,
 "k_factor" numeric NOT NULL DEFAULT '2.00',
 "correction" numeric DEFAULT NULL,
 "max_permissible_error" numeric DEFAULT NULL,
 "ema_conforms" tinyint DEFAULT NULL,
 "reading_order" int NOT NULL DEFAULT '0',
 "repetition" int NOT NULL DEFAULT '1',
 "unit" varchar(10) NOT NULL DEFAULT 'kg',
 "temperature" numeric DEFAULT NULL,
 "humidity" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "ema" numeric DEFAULT NULL,
 "conforms" tinyint DEFAULT NULL
);
CREATE INDEX "calibration_readings_cal_readings_cal_id_order_idx" ON "calibration_readings" ("equipment_calibration_id","reading_order");
CREATE INDEX "calibration_readings_tid_idx" ON "calibration_readings" ("tenant_id");
CREATE INDEX "calibration_readings_tenant_id_idx" ON "calibration_readings" ("tenant_id");

CREATE TABLE "calibration_standard_weight" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "equipment_calibration_id" integer NOT NULL,
 "standard_weight_id" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE UNIQUE INDEX "cal_sw_unique" ON "calibration_standard_weight" ("equipment_calibration_id","standard_weight_id");
CREATE INDEX "calibration_standard_weight_standard_weight_id_foreign" ON "calibration_standard_weight" ("standard_weight_id");
CREATE INDEX "calibration_standard_weight_calibration_standard_tenant_idx" ON "calibration_standard_weight" ("tenant_id");
CREATE INDEX "calibration_standard_weight_tenant_id_idx" ON "calibration_standard_weight" ("tenant_id");

CREATE TABLE "calibration_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "calibration_types_tid_slug_uq" ON "calibration_types" ("tenant_id","slug");
CREATE INDEX "calibration_types_tid_idx" ON "calibration_types" ("tenant_id");
CREATE INDEX "calibration_types_del_idx" ON "calibration_types" ("deleted_at");
CREATE INDEX "calibration_types_deleted_at_idx" ON "calibration_types" ("deleted_at");

CREATE TABLE "cameras" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "name" varchar(255) NOT NULL,
 "stream_url" varchar(255) NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "position" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL,
 "location" varchar(255) DEFAULT NULL,
 "type" varchar(255) NOT NULL DEFAULT 'ip'
);
CREATE INDEX "cameras_tenant_id_index" ON "cameras" ("tenant_id");
CREATE INDEX "cameras_tenant_id_idx" ON "cameras" ("tenant_id");

CREATE TABLE "cancellation_reasons" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "applies_to" text DEFAULT NULL
);
CREATE UNIQUE INDEX "cancellation_reasons_tid_slug_uq" ON "cancellation_reasons" ("tenant_id","slug");
CREATE INDEX "cancellation_reasons_tid_idx" ON "cancellation_reasons" ("tenant_id");
CREATE INDEX "cancellation_reasons_del_idx" ON "cancellation_reasons" ("deleted_at");
CREATE INDEX "cancellation_reasons_deleted_at_idx" ON "cancellation_reasons" ("deleted_at");

CREATE TABLE "candidates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "job_posting_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "email" varchar(255) NOT NULL,
 "phone" varchar(255) DEFAULT NULL,
 "resume_path" varchar(255) DEFAULT NULL,
 "stage" varchar NOT NULL DEFAULT 'applied',
 "notes" text,
 "rating" tinyint DEFAULT NULL,
 "rejected_reason" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "candidates_job_posting_id_foreign" ON "candidates" ("job_posting_id");
CREATE INDEX "candidates_tid_idx" ON "candidates" ("tenant_id");
CREATE INDEX "candidates_tenant_id_idx" ON "candidates" ("tenant_id");

CREATE TABLE "capa_records" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(255) NOT NULL DEFAULT 'corrective',
 "source" varchar(255) NOT NULL,
 "source_id" integer DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "root_cause" text,
 "corrective_action" text,
 "preventive_action" text,
 "verification" text,
 "status" varchar(255) NOT NULL DEFAULT 'open',
 "assigned_to" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "due_date" date DEFAULT NULL,
 "closed_at" datetime NULL DEFAULT NULL,
 "effectiveness" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "capa_records_assigned_to_foreign" ON "capa_records" ("assigned_to");
CREATE INDEX "capa_records_created_by_foreign" ON "capa_records" ("created_by");
CREATE INDEX "capa_records_tenant_id_status_index" ON "capa_records" ("tenant_id","status");
CREATE INDEX "capa_records_source_id_index" ON "capa_records" ("source_id");
CREATE INDEX "capa_records_tenant_id_idx" ON "capa_records" ("tenant_id");

CREATE TABLE "central_attachments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "agenda_item_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "path" varchar(500) NOT NULL,
 "mime_type" varchar(100) DEFAULT NULL,
 "size" integer NOT NULL DEFAULT '0',
 "uploaded_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "central_attachments_uploaded_by_foreign" ON "central_attachments" ("uploaded_by");
CREATE INDEX "central_attachments_agenda_item_id_index" ON "central_attachments" ("agenda_item_id");
CREATE INDEX "central_attachments_tenant_id_index" ON "central_attachments" ("tenant_id");
CREATE INDEX "central_attachments_tenant_id_idx" ON "central_attachments" ("tenant_id");

CREATE TABLE "central_item_comments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "agenda_item_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "body" text NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "central_item_comments_agenda_item_id_index" ON "central_item_comments" ("agenda_item_id");
CREATE INDEX "central_item_comments_cic_user" ON "central_item_comments" ("user_id");
CREATE INDEX "central_item_comments_tenant_id_index" ON "central_item_comments" ("tenant_id");

CREATE TABLE "central_item_dependencies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "item_id" integer NOT NULL,
 "depends_on_id" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE UNIQUE INDEX "central_item_dependencies_item_id_depends_on_id_unique" ON "central_item_dependencies" ("item_id","depends_on_id");
CREATE INDEX "central_item_dependencies_depends_on_id_foreign" ON "central_item_dependencies" ("depends_on_id");
CREATE INDEX "central_item_dependencies_central_item_depen_tenant_idx" ON "central_item_dependencies" ("tenant_id");
CREATE INDEX "central_item_dependencies_tenant_id_idx" ON "central_item_dependencies" ("tenant_id");

CREATE TABLE "central_item_history" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "agenda_item_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "action" varchar(50) NOT NULL,
 "from_value" varchar(255) DEFAULT NULL,
 "to_value" varchar(255) DEFAULT NULL,
 "created_at" datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX "central_item_history_user_id_foreign" ON "central_item_history" ("user_id");
CREATE INDEX "central_item_history_agenda_item_id_index" ON "central_item_history" ("agenda_item_id");
CREATE INDEX "central_item_history_tenant_id_index" ON "central_item_history" ("tenant_id");

CREATE TABLE "central_item_watchers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "agenda_item_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "role" varchar(20) NOT NULL DEFAULT 'watcher',
 "notify_status_change" tinyint NOT NULL DEFAULT '1',
 "notify_comment" tinyint NOT NULL DEFAULT '1',
 "notify_due_date" tinyint NOT NULL DEFAULT '1',
 "notify_assignment" tinyint NOT NULL DEFAULT '1',
 "added_by_type" varchar(20) NOT NULL DEFAULT 'manual',
 "added_by_user_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE UNIQUE INDEX "ciw_item_user_unique" ON "central_item_watchers" ("agenda_item_id","user_id");
CREATE INDEX "central_item_watchers_added_by_user_id_foreign" ON "central_item_watchers" ("added_by_user_id");
CREATE INDEX "central_item_watchers_central_item_watch_tenant_idx" ON "central_item_watchers" ("tenant_id");
CREATE INDEX "central_item_watchers_ciw_user" ON "central_item_watchers" ("user_id");
CREATE INDEX "central_item_watchers_tenant_id_idx" ON "central_item_watchers" ("tenant_id");

CREATE TABLE "central_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(20) NOT NULL,
 "origin" varchar(20) NOT NULL DEFAULT 'manual',
 "ref_type" varchar(100) DEFAULT NULL,
 "ref_id" integer DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "short_description" text,
 "assignee_user_id" integer DEFAULT NULL,
 "created_by_user_id" integer DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'open',
 "priority" varchar(20) NOT NULL DEFAULT 'medium',
 "visibility" varchar(20) NOT NULL DEFAULT 'team',
 "due_at" datetime NULL DEFAULT NULL,
 "remind_at" datetime NULL DEFAULT NULL,
 "snooze_until" datetime NULL DEFAULT NULL,
 "sla_due_at" datetime NULL DEFAULT NULL,
 "closed_at" datetime NULL DEFAULT NULL,
 "closed_by" integer DEFAULT NULL,
 "context" text DEFAULT NULL,
 "tags" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "remind_notified_at" datetime NULL DEFAULT NULL,
 "recurrence_pattern" varchar(30) DEFAULT NULL,
 "recurrence_interval" integer NOT NULL DEFAULT '1',
 "recurrence_next_at" datetime NULL DEFAULT NULL,
 "escalation_hours" integer DEFAULT NULL,
 "visibility_departments" text DEFAULT NULL,
 "visibility_users" text DEFAULT NULL
);
CREATE UNIQUE INDEX "ci_ref_unique" ON "central_items" ("tenant_id","ref_type","ref_id");
CREATE INDEX "central_items_ci_user_status_due" ON "central_items" ("tenant_id","assignee_user_id","status","due_at");
CREATE INDEX "central_items_ci_tipo_status" ON "central_items" ("tenant_id","type","status");
CREATE INDEX "central_items_ci_sla" ON "central_items" ("tenant_id","sla_due_at");
CREATE INDEX "central_items_ci_created" ON "central_items" ("tenant_id","created_at");
CREATE INDEX "central_items_closed_by_foreign" ON "central_items" ("closed_by");
CREATE INDEX "central_items_ci_tenant_status" ON "central_items" ("tenant_id","status");
CREATE INDEX "central_items_responsavel_user_id_foreign" ON "central_items" ("assignee_user_id");
CREATE INDEX "central_items_criado_por_user_id_foreign" ON "central_items" ("created_by_user_id");
CREATE INDEX "central_items_ci_deleted_at" ON "central_items" ("tenant_id","deleted_at");
CREATE INDEX "central_items_del_idx" ON "central_items" ("deleted_at");
CREATE INDEX "central_items_deleted_at_idx" ON "central_items" ("deleted_at");

CREATE TABLE "central_notification_prefs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "user_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "notify_assigned_to_me" tinyint NOT NULL DEFAULT '1',
 "notify_created_by_me" tinyint NOT NULL DEFAULT '1',
 "notify_watching" tinyint NOT NULL DEFAULT '1',
 "notify_mentioned" tinyint NOT NULL DEFAULT '1',
 "channel_in_app" varchar(10) NOT NULL DEFAULT 'on',
 "channel_email" varchar(10) NOT NULL DEFAULT 'off',
 "channel_push" varchar(10) NOT NULL DEFAULT 'on',
 "digest_frequency" varchar(20) DEFAULT NULL,
 "quiet_hours" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "pwa_mode" varchar(20) DEFAULT NULL,
 "notify_types" text DEFAULT NULL
);
CREATE UNIQUE INDEX "cnp_user_tenant" ON "central_notification_prefs" ("user_id","tenant_id");
CREATE INDEX "central_notification_prefs_tid_idx" ON "central_notification_prefs" ("tenant_id");
CREATE INDEX "central_notification_prefs_tenant_id_idx" ON "central_notification_prefs" ("tenant_id");

CREATE TABLE "central_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(100) NOT NULL,
 "description" varchar(500) DEFAULT NULL,
 "active" tinyint NOT NULL DEFAULT '1',
 "event_trigger" varchar(255) DEFAULT NULL,
 "item_type" varchar(255) DEFAULT NULL,
 "status_trigger" varchar(255) DEFAULT NULL,
 "min_priority" varchar(255) DEFAULT NULL,
 "action_type" varchar(255) NOT NULL,
 "action_config" text DEFAULT NULL,
 "assignee_user_id" integer DEFAULT NULL,
 "target_role" varchar(255) DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "central_rules_tenant_id_index" ON "central_rules" ("tenant_id");
CREATE INDEX "central_rules_ativo_index" ON "central_rules" ("active");
CREATE INDEX "central_rules_evento_trigger_index" ON "central_rules" ("event_trigger");
CREATE INDEX "central_rules_created_by_foreign" ON "central_rules" ("created_by");
CREATE INDEX "central_rules_responsavel_user_id_foreign" ON "central_rules" ("assignee_user_id");
CREATE INDEX "central_rules_tenant_id_idx" ON "central_rules" ("tenant_id");

CREATE TABLE "central_subtasks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "agenda_item_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "is_completed" tinyint NOT NULL DEFAULT '0',
 "sort_order" integer NOT NULL DEFAULT '0',
 "completed_by" integer DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "central_subtasks_completed_by_foreign" ON "central_subtasks" ("completed_by");
CREATE INDEX "central_subtasks_agenda_item_id_ordem_index" ON "central_subtasks" ("agenda_item_id","sort_order");
CREATE INDEX "central_subtasks_tenant_id_index" ON "central_subtasks" ("tenant_id");
CREATE INDEX "central_subtasks_tenant_id_idx" ON "central_subtasks" ("tenant_id");

CREATE TABLE "central_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(150) NOT NULL,
 "description" text,
 "type" varchar(20) NOT NULL DEFAULT 'TAREFA',
 "priority" varchar(20) NOT NULL DEFAULT 'medium',
 "visibility" varchar(20) NOT NULL DEFAULT 'team',
 "category" varchar(60) DEFAULT NULL,
 "due_days" int DEFAULT NULL,
 "subtasks" text DEFAULT NULL,
 "default_watchers" text DEFAULT NULL,
 "tags" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "central_templates_created_by_foreign" ON "central_templates" ("created_by");
CREATE INDEX "central_templates_ct_tenant_active" ON "central_templates" ("tenant_id","is_active");
CREATE INDEX "central_templates_tenant_id_idx" ON "central_templates" ("tenant_id");

CREATE TABLE "central_time_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "agenda_item_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "started_at" datetime NOT NULL,
 "stopped_at" datetime NULL DEFAULT NULL,
 "duration_seconds" int NOT NULL DEFAULT '0',
 "description" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "central_time_entries_user_id_foreign" ON "central_time_entries" ("user_id");
CREATE INDEX "central_time_entries_agenda_item_id_user_id_index" ON "central_time_entries" ("agenda_item_id","user_id");
CREATE INDEX "central_time_entries_tenant_id_index" ON "central_time_entries" ("tenant_id");
CREATE INDEX "central_time_entries_tenant_id_idx" ON "central_time_entries" ("tenant_id");

CREATE TABLE "certificate_emission_checklists" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_calibration_id" integer NOT NULL,
 "verified_by" integer NOT NULL,
 "equipment_identified" tinyint NOT NULL DEFAULT '0',
 "scope_defined" tinyint NOT NULL DEFAULT '0',
 "critical_analysis_done" tinyint NOT NULL DEFAULT '0',
 "procedure_defined" tinyint NOT NULL DEFAULT '0',
 "standards_traceable" tinyint NOT NULL DEFAULT '0',
 "raw_data_recorded" tinyint NOT NULL DEFAULT '0',
 "uncertainty_calculated" tinyint NOT NULL DEFAULT '0',
 "adjustment_documented" tinyint NOT NULL DEFAULT '0',
 "no_undue_interval" tinyint NOT NULL DEFAULT '0',
 "conformity_declaration_valid" tinyint NOT NULL DEFAULT '0',
 "accreditation_mark_correct" tinyint NOT NULL DEFAULT '0',
 "observations" text,
 "approved" tinyint NOT NULL DEFAULT '0',
 "verified_at" datetime DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "cert_checklist_cal_unique" ON "certificate_emission_checklists" ("equipment_calibration_id");
CREATE INDEX "certificate_emission_checklists_verified_by_foreign" ON "certificate_emission_checklists" ("verified_by");
CREATE INDEX "certificate_emission_checklists_tenant_id_index" ON "certificate_emission_checklists" ("tenant_id");

CREATE TABLE "certificate_signatures" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "certificate_id" integer NOT NULL,
 "signer_name" varchar(255) NOT NULL,
 "signer_role" varchar(255) NOT NULL,
 "signed_at" datetime NULL DEFAULT NULL,
 "signature_hash" varchar(255) DEFAULT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "certificate_signatures_tid_idx" ON "certificate_signatures" ("tenant_id");
CREATE INDEX "certificate_signatures_certificate_id_index" ON "certificate_signatures" ("certificate_id");
CREATE INDEX "certificate_signatures_tenant_id_idx" ON "certificate_signatures" ("tenant_id");

CREATE TABLE "certificate_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "type" varchar(255) NOT NULL DEFAULT 'calibration',
 "header_html" text,
 "footer_html" text,
 "logo_path" varchar(255) DEFAULT NULL,
 "signature_image_path" varchar(255) DEFAULT NULL,
 "signatory_name" varchar(255) DEFAULT NULL,
 "signatory_title" varchar(255) DEFAULT NULL,
 "signatory_registration" varchar(255) DEFAULT NULL,
 "custom_fields" text DEFAULT NULL,
 "is_default" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "certificate_templates_tid_idx" ON "certificate_templates" ("tenant_id");
CREATE INDEX "certificate_templates_tenant_id_idx" ON "certificate_templates" ("tenant_id");

CREATE TABLE "chart_of_accounts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "parent_id" integer DEFAULT NULL,
 "code" varchar(20) NOT NULL,
 "name" varchar(255) NOT NULL,
 "type" varchar(20) NOT NULL,
 "is_system" tinyint NOT NULL DEFAULT '0',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "chart_of_accounts_tenant_id_code_unique" ON "chart_of_accounts" ("tenant_id","code");
CREATE INDEX "chart_of_accounts_parent_id_foreign" ON "chart_of_accounts" ("parent_id");

CREATE TABLE "chat_messages" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "ticket_id" integer NOT NULL,
 "sender_id" integer NOT NULL,
 "sender_type" varchar(20) NOT NULL,
 "message" text NOT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "chat_messages_tenant_id_index" ON "chat_messages" ("tenant_id");
CREATE INDEX "chat_messages_ticket_id_index" ON "chat_messages" ("ticket_id");
CREATE INDEX "chat_messages_sender_id_foreign" ON "chat_messages" ("sender_id");
CREATE INDEX "chat_messages_tenant_id_idx" ON "chat_messages" ("tenant_id");

CREATE TABLE "checklist_submissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "checklist_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "technician_id" integer NOT NULL,
 "responses" text NOT NULL,
 "completed_at" datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "checklist_submissions_checklist_id_foreign" ON "checklist_submissions" ("checklist_id");
CREATE INDEX "checklist_submissions_work_order_id_foreign" ON "checklist_submissions" ("work_order_id");
CREATE INDEX "checklist_submissions_technician_id_foreign" ON "checklist_submissions" ("technician_id");
CREATE INDEX "checklist_submissions_tid_idx" ON "checklist_submissions" ("tenant_id");
CREATE INDEX "checklist_submissions_tenant_id_idx" ON "checklist_submissions" ("tenant_id");

CREATE TABLE "checklists" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "items" text NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "checklists_tid_idx" ON "checklists" ("tenant_id");
CREATE INDEX "checklists_tenant_id_idx" ON "checklists" ("tenant_id");

CREATE TABLE "client_portal_users" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "email" varchar(255) NOT NULL,
 "password" varchar(255) NOT NULL,
 "password_changed_at" datetime NULL DEFAULT NULL,
 "password_history" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "failed_login_attempts" int NOT NULL DEFAULT '0',
 "locked_until" datetime NULL DEFAULT NULL,
 "two_factor_enabled" tinyint NOT NULL DEFAULT '0',
 "two_factor_secret" text,
 "two_factor_recovery_codes" text DEFAULT NULL,
 "two_factor_confirmed_at" datetime NULL DEFAULT NULL,
 "last_login_at" datetime NULL DEFAULT NULL,
 "remember_token" varchar(100) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "client_portal_users_tenant_id_email_unique" ON "client_portal_users" ("tenant_id","email");
CREATE INDEX "client_portal_users_customer_id_foreign" ON "client_portal_users" ("customer_id");

CREATE TABLE "clt_violations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "date" date NOT NULL,
 "violation_type" varchar NOT NULL,
 "severity" varchar NOT NULL,
 "description" varchar(500) NOT NULL,
 "resolved" tinyint NOT NULL DEFAULT '0',
 "resolved_at" datetime DEFAULT NULL,
 "resolved_by" integer DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "clt_violations_user_id_foreign" ON "clt_violations" ("user_id");
CREATE INDEX "clt_violations_resolved_by_foreign" ON "clt_violations" ("resolved_by");
CREATE INDEX "clt_violations_tenant_id_idx" ON "clt_violations" ("tenant_id");

CREATE TABLE "collection_action_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "receivable_id" integer NOT NULL,
 "rule_id" integer DEFAULT NULL,
 "channel" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'sent',
 "message" text,
 "error" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "collection_action_logs_receivable_id_foreign" ON "collection_action_logs" ("receivable_id");
CREATE INDEX "collection_action_logs_rule_id_foreign" ON "collection_action_logs" ("rule_id");
CREATE INDEX "collection_action_logs_tenant_id_receivable_id_index" ON "collection_action_logs" ("tenant_id","receivable_id");
CREATE INDEX "collection_action_logs_tenant_id_idx" ON "collection_action_logs" ("tenant_id");

CREATE TABLE "collection_actions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "account_receivable_id" integer NOT NULL,
 "collection_rule_id" integer DEFAULT NULL,
 "step_index" int NOT NULL DEFAULT '0',
 "channel" varchar(30) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "scheduled_at" datetime NOT NULL,
 "sent_at" datetime DEFAULT NULL,
 "response" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "collection_actions_account_receivable_id_foreign" ON "collection_actions" ("account_receivable_id");
CREATE INDEX "collection_actions_collection_rule_id_foreign" ON "collection_actions" ("collection_rule_id");
CREATE INDEX "collection_actions_ca_tenant_status_scheduled" ON "collection_actions" ("tenant_id","status","scheduled_at");
CREATE INDEX "collection_actions_tid_idx" ON "collection_actions" ("tenant_id");
CREATE INDEX "collection_actions_tenant_id_idx" ON "collection_actions" ("tenant_id");

CREATE TABLE "collection_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "account_receivable_id" integer NOT NULL,
 "collection_rule_id" integer NOT NULL,
 "channel" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'sent',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "collection_logs_collection_rule_id_foreign" ON "collection_logs" ("collection_rule_id");
CREATE INDEX "collection_logs_tenant_id_created_at_index" ON "collection_logs" ("tenant_id","created_at");
CREATE INDEX "collection_logs_cl_ar_status" ON "collection_logs" ("account_receivable_id","status");
CREATE INDEX "collection_logs_tenant_id_idx" ON "collection_logs" ("tenant_id");

CREATE TABLE "collection_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "steps" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "collection_rules_tid_idx" ON "collection_rules" ("tenant_id");
CREATE INDEX "collection_rules_tenant_id_idx" ON "collection_rules" ("tenant_id");

CREATE TABLE "commission_campaigns" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "multiplier" numeric NOT NULL DEFAULT '1.00',
 "applies_to_role" varchar(20) DEFAULT NULL,
 "applies_to_calculation_type" varchar(50) DEFAULT NULL,
 "starts_at" date NOT NULL,
 "ends_at" date NOT NULL,
 "active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "is_active" tinyint DEFAULT NULL
);
CREATE INDEX "commission_campaigns_tid_idx" ON "commission_campaigns" ("tenant_id");
CREATE INDEX "commission_campaigns_tenant_id_idx" ON "commission_campaigns" ("tenant_id");

CREATE TABLE "commission_disputes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "commission_event_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "reason" text NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'open',
 "resolution_notes" text,
 "resolved_by" integer DEFAULT NULL,
 "resolved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "commission_disputes_commission_event_id_foreign" ON "commission_disputes" ("commission_event_id");
CREATE INDEX "commission_disputes_tenant_id_status_index" ON "commission_disputes" ("tenant_id","status");
CREATE INDEX "commission_disputes_user_id_foreign" ON "commission_disputes" ("user_id");
CREATE INDEX "commission_disputes_tenant_id_idx" ON "commission_disputes" ("tenant_id");

CREATE TABLE "commission_events" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "commission_rule_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "base_amount" numeric NOT NULL,
 "commission_amount" numeric NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "account_receivable_id" integer DEFAULT NULL,
 "proportion" numeric NOT NULL DEFAULT '1.0000',
 "settlement_id" integer DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "amount" numeric DEFAULT NULL
);
CREATE INDEX "commission_events_tenant_id_user_id_status_index" ON "commission_events" ("tenant_id","user_id","status");
CREATE INDEX "commission_events_account_receivable_id_foreign" ON "commission_events" ("account_receivable_id");
CREATE INDEX "commission_events_settlement_id_index" ON "commission_events" ("settlement_id");
CREATE INDEX "commission_events_ce_work_order" ON "commission_events" ("work_order_id");
CREATE INDEX "commission_events_ce_tenant_created" ON "commission_events" ("tenant_id","created_at");
CREATE INDEX "commission_events_user_id_foreign" ON "commission_events" ("user_id");
CREATE INDEX "commission_events_ce_tenant_status_created" ON "commission_events" ("tenant_id","status","created_at");
CREATE INDEX "commission_events_ce_tenant_user" ON "commission_events" ("tenant_id","user_id");
CREATE INDEX "commission_events_ce_wo_status" ON "commission_events" ("work_order_id","status");
CREATE INDEX "commission_events_del_idx" ON "commission_events" ("deleted_at");
CREATE INDEX "commission_events_commission_rule_id_fk_idx" ON "commission_events" ("commission_rule_id");
CREATE INDEX "commission_events_work_order_id_fk_idx" ON "commission_events" ("work_order_id");
CREATE INDEX "commission_events_tenant_id_idx" ON "commission_events" ("tenant_id");
CREATE INDEX "commission_events_deleted_at_idx" ON "commission_events" ("deleted_at");

CREATE TABLE "commission_goals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "period" varchar(7) NOT NULL,
 "type" varchar(30) NOT NULL DEFAULT 'revenue',
 "target_amount" numeric NOT NULL,
 "achieved_amount" numeric NOT NULL DEFAULT '0.00',
 "bonus_percentage" numeric DEFAULT NULL,
 "bonus_amount" numeric DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "bonus_rules" text DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'active',
 "target_value" numeric DEFAULT NULL,
 "current_value" numeric DEFAULT NULL
);
CREATE UNIQUE INDEX "commission_goals_tenant_user_period_type_unique" ON "commission_goals" ("tenant_id","user_id","period","type");
CREATE INDEX "commission_goals_tenant_id_period_index" ON "commission_goals" ("tenant_id","period");
CREATE INDEX "commission_goals_user_id_foreign" ON "commission_goals" ("user_id");

CREATE TABLE "commission_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "type" varchar(20) NOT NULL DEFAULT 'percentage',
 "value" numeric NOT NULL,
 "applies_to" varchar(20) NOT NULL DEFAULT 'all',
 "active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "calculation_type" varchar(40) NOT NULL DEFAULT 'percent_gross',
 "applies_to_role" varchar(20) NOT NULL DEFAULT 'tecnico',
 "applies_when" varchar(20) NOT NULL DEFAULT 'os_completed',
 "tiers" text DEFAULT NULL,
 "priority" int NOT NULL DEFAULT '0',
 "source_filter" varchar(50) DEFAULT NULL,
 "percentage" numeric DEFAULT NULL,
 "fixed_amount" numeric DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "commission_rules_tenant_id_user_id_index" ON "commission_rules" ("tenant_id","user_id");
CREATE INDEX "commission_rules_user_id_foreign" ON "commission_rules" ("user_id");
CREATE INDEX "commission_rules_tenant_id_idx" ON "commission_rules" ("tenant_id");
CREATE INDEX "commission_rules_deleted_at_idx" ON "commission_rules" ("deleted_at");

CREATE TABLE "commission_settlements" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "period" varchar(7) NOT NULL,
 "total_amount" numeric NOT NULL,
 "events_count" int NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'open',
 "paid_at" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "closed_by" integer DEFAULT NULL,
 "closed_at" datetime NULL DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "rejection_reason" text,
 "paid_amount" numeric DEFAULT NULL,
 "payment_notes" text,
 "total" numeric DEFAULT NULL
);
CREATE UNIQUE INDEX "commission_settlements_tenant_id_user_id_period_unique" ON "commission_settlements" ("tenant_id","user_id","period");
CREATE INDEX "commission_settlements_closed_by_foreign" ON "commission_settlements" ("closed_by");
CREATE INDEX "commission_settlements_approved_by_foreign" ON "commission_settlements" ("approved_by");
CREATE INDEX "commission_settlements_cs_tenant_status" ON "commission_settlements" ("tenant_id","status");
CREATE INDEX "commission_settlements_user_id_foreign" ON "commission_settlements" ("user_id");
CREATE INDEX "commission_settlements_cset_tenant_user_status" ON "commission_settlements" ("tenant_id","user_id","status");
CREATE INDEX "commission_settlements_comset_tid_st_idx" ON "commission_settlements" ("tenant_id","status");

CREATE TABLE "commission_splits" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "commission_event_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "percentage" numeric NOT NULL,
 "amount" numeric NOT NULL,
 "role" varchar(20) NOT NULL DEFAULT 'tecnico',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "commission_splits_commission_event_id_index" ON "commission_splits" ("commission_event_id");
CREATE INDEX "commission_splits_cs_event" ON "commission_splits" ("commission_event_id");
CREATE INDEX "commission_splits_tid_idx" ON "commission_splits" ("tenant_id");
CREATE INDEX "commission_splits_user_id_fk_idx" ON "commission_splits" ("user_id");
CREATE INDEX "commission_splits_tenant_id_idx" ON "commission_splits" ("tenant_id");

CREATE TABLE "commitments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "visit_report_id" integer DEFAULT NULL,
 "activity_id" integer DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "responsible_type" varchar(255) NOT NULL,
 "responsible_name" varchar(255) DEFAULT NULL,
 "due_date" date DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "completed_at" datetime NULL DEFAULT NULL,
 "completion_notes" text,
 "priority" varchar(255) NOT NULL DEFAULT 'normal',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "commitments_customer_id_foreign" ON "commitments" ("customer_id");
CREATE INDEX "commitments_user_id_foreign" ON "commitments" ("user_id");
CREATE INDEX "commitments_visit_report_id_foreign" ON "commitments" ("visit_report_id");
CREATE INDEX "commitments_activity_id_foreign" ON "commitments" ("activity_id");
CREATE INDEX "commitments_commit_tenant_status_due_idx" ON "commitments" ("tenant_id","status","due_date");
CREATE INDEX "commitments_commit_tenant_cust_idx" ON "commitments" ("tenant_id","customer_id");
CREATE INDEX "commitments_tenant_id_idx" ON "commitments" ("tenant_id");

CREATE TABLE "competitor_instrument_repairs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "competitor_id" integer NOT NULL,
 "instrument_id" integer NOT NULL,
 "repair_date" date NOT NULL,
 "seal_number" varchar(50) DEFAULT NULL,
 "notes" text,
 "source" varchar(30) NOT NULL DEFAULT 'xml_import',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "competitor_instrument_repairs_competitor_id_repair_date_index" ON "competitor_instrument_repairs" ("competitor_id","repair_date");
CREATE INDEX "competitor_instrument_repairs_instrument_id_repair_date_index" ON "competitor_instrument_repairs" ("instrument_id","repair_date");

CREATE TABLE "contact_policies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "target_type" varchar(255) NOT NULL,
 "target_value" varchar(255) DEFAULT NULL,
 "max_days_without_contact" int NOT NULL,
 "warning_days_before" int NOT NULL DEFAULT '7',
 "preferred_contact_type" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "priority" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "contact_policies_cp_tenant_active_idx" ON "contact_policies" ("tenant_id","is_active");
CREATE INDEX "contact_policies_tenant_id_idx" ON "contact_policies" ("tenant_id");

CREATE TABLE "continuous_feedback" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "from_user_id" integer NOT NULL,
 "to_user_id" integer NOT NULL,
 "type" varchar NOT NULL DEFAULT 'praise',
 "content" text NOT NULL,
 "is_anonymous" tinyint NOT NULL DEFAULT '0',
 "visibility" varchar NOT NULL DEFAULT 'private',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "attachment_path" varchar(255) DEFAULT NULL
);
CREATE INDEX "continuous_feedback_from_user_id_foreign" ON "continuous_feedback" ("from_user_id");
CREATE INDEX "continuous_feedback_to_user_id_foreign" ON "continuous_feedback" ("to_user_id");
CREATE INDEX "continuous_feedback_tid_idx" ON "continuous_feedback" ("tenant_id");
CREATE INDEX "continuous_feedback_tenant_id_idx" ON "continuous_feedback" ("tenant_id");

CREATE TABLE "contract_addendums" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "contract_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "description" text NOT NULL,
 "new_value" numeric DEFAULT NULL,
 "new_end_date" date DEFAULT NULL,
 "effective_date" date NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "created_by" integer NOT NULL,
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "contract_addendums_created_by_foreign" ON "contract_addendums" ("created_by");
CREATE INDEX "contract_addendums_approved_by_foreign" ON "contract_addendums" ("approved_by");
CREATE INDEX "contract_addendums_tid_idx" ON "contract_addendums" ("tenant_id");
CREATE INDEX "contract_addendums_contract_id_fk_idx" ON "contract_addendums" ("contract_id");
CREATE INDEX "contract_addendums_tenant_id_idx" ON "contract_addendums" ("tenant_id");

CREATE TABLE "contract_adjustments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "contract_id" integer NOT NULL,
 "old_value" numeric NOT NULL,
 "new_value" numeric NOT NULL,
 "index_rate" numeric NOT NULL,
 "effective_date" date NOT NULL,
 "applied_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "contract_adjustments_applied_by_foreign" ON "contract_adjustments" ("applied_by");
CREATE INDEX "contract_adjustments_contract_id_foreign" ON "contract_adjustments" ("contract_id");
CREATE INDEX "contract_adjustments_tid_idx" ON "contract_adjustments" ("tenant_id");
CREATE INDEX "contract_adjustments_tenant_id_idx" ON "contract_adjustments" ("tenant_id");

CREATE TABLE "contract_measurements" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "contract_id" integer NOT NULL,
 "period" varchar(255) NOT NULL,
 "items" text NOT NULL,
 "total_accepted" numeric NOT NULL DEFAULT '0.00',
 "total_rejected" numeric NOT NULL DEFAULT '0.00',
 "notes" text,
 "status" varchar(255) NOT NULL DEFAULT 'pending_approval',
 "created_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "contract_measurements_created_by_foreign" ON "contract_measurements" ("created_by");
CREATE INDEX "contract_measurements_cm_contract" ON "contract_measurements" ("contract_id");
CREATE INDEX "contract_measurements_tid_idx" ON "contract_measurements" ("tenant_id");
CREATE INDEX "contract_measurements_tenant_id_idx" ON "contract_measurements" ("tenant_id");

CREATE TABLE "contract_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "contract_types_tid_slug_uq" ON "contract_types" ("tenant_id","slug");
CREATE INDEX "contract_types_tid_idx" ON "contract_types" ("tenant_id");
CREATE INDEX "contract_types_del_idx" ON "contract_types" ("deleted_at");
CREATE INDEX "contract_types_deleted_at_idx" ON "contract_types" ("deleted_at");

CREATE TABLE "contracts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "number" varchar(30) DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "status" varchar(20) NOT NULL DEFAULT 'active',
 "start_date" date DEFAULT NULL,
 "end_date" date DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "contracts_tenant_id_is_active_index" ON "contracts" ("tenant_id","is_active");
CREATE INDEX "contracts_del_idx" ON "contracts" ("deleted_at");
CREATE INDEX "contracts_customer_id_fk_idx" ON "contracts" ("customer_id");
CREATE INDEX "contracts_tid_st_idx" ON "contracts" ("tenant_id","status");
CREATE INDEX "contracts_tenant_id_idx" ON "contracts" ("tenant_id");
CREATE INDEX "contracts_deleted_at_idx" ON "contracts" ("deleted_at");

CREATE TABLE "corrective_actions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(10) NOT NULL DEFAULT 'corrective',
 "source" varchar(50) NOT NULL,
 "sourceable_type" varchar(255) DEFAULT NULL,
 "sourceable_id" integer DEFAULT NULL,
 "nonconformity_description" text NOT NULL,
 "root_cause" text,
 "action_plan" text,
 "responsible_id" integer DEFAULT NULL,
 "deadline" date DEFAULT NULL,
 "completed_at" date DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'open',
 "verification_notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "corrective_actions_sourceable_type_sourceable_id_index" ON "corrective_actions" ("sourceable_type","sourceable_id");
CREATE INDEX "corrective_actions_responsible_id_foreign" ON "corrective_actions" ("responsible_id");
CREATE INDEX "corrective_actions_corract_sourceable_poly" ON "corrective_actions" ("sourceable_type","sourceable_id");
CREATE INDEX "corrective_actions_tid_idx" ON "corrective_actions" ("tenant_id");
CREATE INDEX "corrective_actions_tid_st_idx" ON "corrective_actions" ("tenant_id","status");
CREATE INDEX "corrective_actions_tenant_id_idx" ON "corrective_actions" ("tenant_id");

CREATE TABLE "cost_centers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "code" varchar(20) DEFAULT NULL,
 "parent_id" integer DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "cost_centers_parent_id_foreign" ON "cost_centers" ("parent_id");
CREATE INDEX "cost_centers_code_index" ON "cost_centers" ("code");
CREATE INDEX "cost_centers_tid_idx" ON "cost_centers" ("tenant_id");
CREATE INDEX "cost_centers_del_idx" ON "cost_centers" ("deleted_at");
CREATE INDEX "cost_centers_tenant_id_idx" ON "cost_centers" ("tenant_id");
CREATE INDEX "cost_centers_deleted_at_idx" ON "cost_centers" ("deleted_at");

CREATE TABLE "crm_activities" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "customer_id" integer NOT NULL,
 "deal_id" integer DEFAULT NULL,
 "user_id" integer DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "scheduled_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "duration_minutes" int DEFAULT NULL,
 "outcome" varchar(255) DEFAULT NULL,
 "is_automated" tinyint NOT NULL DEFAULT '0',
 "channel" varchar(255) DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "contact_id" integer DEFAULT NULL
);
CREATE INDEX "crm_activities_customer_id_foreign" ON "crm_activities" ("customer_id");
CREATE INDEX "crm_activities_user_id_foreign" ON "crm_activities" ("user_id");
CREATE INDEX "crm_activities_tenant_id_customer_id_index" ON "crm_activities" ("tenant_id","customer_id");
CREATE INDEX "crm_activities_tenant_id_deal_id_index" ON "crm_activities" ("tenant_id","deal_id");
CREATE INDEX "crm_activities_tenant_id_type_index" ON "crm_activities" ("tenant_id","type");
CREATE INDEX "crm_activities_crm_act_tenant_contact_idx" ON "crm_activities" ("tenant_id","contact_id");
CREATE INDEX "crm_activities_crma_deal" ON "crm_activities" ("deal_id");
CREATE INDEX "crm_activities_crma_contact" ON "crm_activities" ("contact_id");
CREATE INDEX "crm_activities_tenant_id_idx" ON "crm_activities" ("tenant_id");

CREATE TABLE "crm_calendar_events" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "type" varchar(255) NOT NULL DEFAULT 'meeting',
 "start_at" datetime NOT NULL,
 "end_at" datetime NOT NULL,
 "all_day" tinyint NOT NULL DEFAULT '0',
 "location" varchar(255) DEFAULT NULL,
 "customer_id" integer DEFAULT NULL,
 "deal_id" integer DEFAULT NULL,
 "activity_id" integer DEFAULT NULL,
 "color" varchar(255) DEFAULT NULL,
 "recurrence_rule" varchar(255) DEFAULT NULL,
 "external_id" varchar(255) DEFAULT NULL,
 "external_provider" varchar(255) DEFAULT NULL,
 "reminders" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_calendar_events_user_id_foreign" ON "crm_calendar_events" ("user_id");
CREATE INDEX "crm_calendar_events_customer_id_foreign" ON "crm_calendar_events" ("customer_id");
CREATE INDEX "crm_calendar_events_deal_id_foreign" ON "crm_calendar_events" ("deal_id");
CREATE INDEX "crm_calendar_events_activity_id_foreign" ON "crm_calendar_events" ("activity_id");
CREATE INDEX "crm_calendar_events_crm_cal_tenant_user_start_idx" ON "crm_calendar_events" ("tenant_id","user_id","start_at");
CREATE INDEX "crm_calendar_events_external_id_index" ON "crm_calendar_events" ("external_id");
CREATE INDEX "crm_calendar_events_tenant_id_idx" ON "crm_calendar_events" ("tenant_id");

CREATE TABLE "crm_contract_renewals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "deal_id" integer DEFAULT NULL,
 "contract_end_date" date NOT NULL,
 "alert_days_before" int NOT NULL DEFAULT '60',
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "current_value" numeric NOT NULL DEFAULT '0.00',
 "renewal_value" numeric DEFAULT NULL,
 "notes" text,
 "notified_at" datetime NULL DEFAULT NULL,
 "renewed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_contract_renewals_customer_id_foreign" ON "crm_contract_renewals" ("customer_id");
CREATE INDEX "crm_contract_renewals_deal_id_foreign" ON "crm_contract_renewals" ("deal_id");
CREATE INDEX "crm_contract_renewals_crm_renew_tenant_status_end_idx" ON "crm_contract_renewals" ("tenant_id","status","contract_end_date");
CREATE INDEX "crm_contract_renewals_tenant_id_idx" ON "crm_contract_renewals" ("tenant_id");

CREATE TABLE "crm_deal_competitors" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL DEFAULT '0',
 "deal_id" integer NOT NULL,
 "competitor_name" varchar(255) NOT NULL,
 "competitor_price" numeric DEFAULT NULL,
 "strengths" text,
 "weaknesses" text,
 "outcome" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_deal_competitors_cdc_deal" ON "crm_deal_competitors" ("deal_id");
CREATE INDEX "crm_deal_competitors_tenant_id_index" ON "crm_deal_competitors" ("tenant_id");

CREATE TABLE "crm_deal_products" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "deal_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity" numeric NOT NULL DEFAULT '1.00',
 "unit_price" numeric NOT NULL DEFAULT '0.00',
 "total" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_deal_products_crm_dp_tenant_deal_idx" ON "crm_deal_products" ("tenant_id","deal_id");
CREATE INDEX "crm_deal_products_tenant_id_idx" ON "crm_deal_products" ("tenant_id");

CREATE TABLE "crm_deal_stage_histories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "deal_id" integer NOT NULL,
 "from_stage_id" integer DEFAULT NULL,
 "to_stage_id" integer NOT NULL,
 "changed_by" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_deal_stage_histories_crm_dsh_tenant_deal_idx" ON "crm_deal_stage_histories" ("tenant_id","deal_id");
CREATE INDEX "crm_deal_stage_histories_tenant_id_idx" ON "crm_deal_stage_histories" ("tenant_id");

CREATE TABLE "crm_deals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "pipeline_id" integer NOT NULL,
 "stage_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "value" numeric NOT NULL DEFAULT '0.00',
 "probability" int NOT NULL DEFAULT '0',
 "expected_close_date" date DEFAULT NULL,
 "source" varchar(255) DEFAULT NULL,
 "assigned_to" integer DEFAULT NULL,
 "quote_id" integer DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "equipment_id" integer DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'open',
 "score" numeric DEFAULT NULL,
 "won_at" datetime NULL DEFAULT NULL,
 "lost_at" datetime NULL DEFAULT NULL,
 "lost_reason" varchar(255) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "loss_reason_id" integer DEFAULT NULL,
 "competitor_name" varchar(255) DEFAULT NULL,
 "competitor_price" numeric DEFAULT NULL
);
CREATE INDEX "crm_deals_customer_id_foreign" ON "crm_deals" ("customer_id");
CREATE INDEX "crm_deals_pipeline_id_foreign" ON "crm_deals" ("pipeline_id");
CREATE INDEX "crm_deals_stage_id_foreign" ON "crm_deals" ("stage_id");
CREATE INDEX "crm_deals_assigned_to_foreign" ON "crm_deals" ("assigned_to");
CREATE INDEX "crm_deals_tenant_id_status_index" ON "crm_deals" ("tenant_id","status");
CREATE INDEX "crm_deals_tenant_id_pipeline_id_stage_id_index" ON "crm_deals" ("tenant_id","pipeline_id","stage_id");
CREATE INDEX "crm_deals_loss_reason_id_foreign" ON "crm_deals" ("loss_reason_id");
CREATE INDEX "crm_deals_tenant_cust_idx" ON "crm_deals" ("tenant_id","customer_id");
CREATE INDEX "crm_deals_tenant_assigned" ON "crm_deals" ("tenant_id","assigned_to");
CREATE INDEX "crm_deals_tenant_stage" ON "crm_deals" ("tenant_id","stage_id");
CREATE INDEX "crm_deals_deals_deleted_at" ON "crm_deals" ("tenant_id","deleted_at");
CREATE INDEX "crm_deals_del_idx" ON "crm_deals" ("deleted_at");
CREATE INDEX "crm_deals_quote_id_fk_idx" ON "crm_deals" ("quote_id");
CREATE INDEX "crm_deals_work_order_id_fk_idx" ON "crm_deals" ("work_order_id");
CREATE INDEX "crm_deals_equipment_id_fk_idx" ON "crm_deals" ("equipment_id");
CREATE INDEX "crm_deals_tenant_id_idx" ON "crm_deals" ("tenant_id");
CREATE INDEX "crm_deals_deleted_at_idx" ON "crm_deals" ("deleted_at");

CREATE TABLE "crm_email_threads" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer DEFAULT NULL,
 "message_id_hash" varchar(64) DEFAULT NULL,
 "subject" varchar(255) NOT NULL,
 "body_text" text,
 "from_email" varchar(255) DEFAULT NULL,
 "date" datetime NULL DEFAULT NULL,
 "direction" varchar(255) NOT NULL DEFAULT 'inbound',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crm_email_threads_message_id_hash_unique" ON "crm_email_threads" ("message_id_hash");
CREATE INDEX "crm_email_threads_customer_id_foreign" ON "crm_email_threads" ("customer_id");
CREATE INDEX "crm_email_threads_tid_idx" ON "crm_email_threads" ("tenant_id");
CREATE INDEX "crm_email_threads_tenant_id_idx" ON "crm_email_threads" ("tenant_id");

CREATE TABLE "crm_external_leads" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "tax_id" varchar(18) DEFAULT NULL,
 "company_name" varchar(255) NOT NULL,
 "rival_company_name" varchar(255) DEFAULT NULL,
 "next_calibration_due" date DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "source" varchar(255) NOT NULL DEFAULT 'inmetro_crawler',
 "status" varchar(255) NOT NULL DEFAULT 'new',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_external_leads_tid_idx" ON "crm_external_leads" ("tenant_id");
CREATE INDEX "crm_external_leads_tax_id_index" ON "crm_external_leads" ("tax_id");
CREATE INDEX "crm_external_leads_tenant_id_idx" ON "crm_external_leads" ("tenant_id");

CREATE TABLE "crm_follow_up_tasks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "deal_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "due_at" datetime NULL DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_follow_up_tasks_crm_fut_tenant_deal_idx" ON "crm_follow_up_tasks" ("tenant_id","deal_id");
CREATE INDEX "crm_follow_up_tasks_tenant_id_idx" ON "crm_follow_up_tasks" ("tenant_id");

CREATE TABLE "crm_forecast_snapshots" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "snapshot_date" date NOT NULL,
 "period_type" varchar(255) NOT NULL DEFAULT 'monthly',
 "period_start" date NOT NULL,
 "period_end" date NOT NULL,
 "pipeline_value" numeric NOT NULL DEFAULT '0.00',
 "weighted_value" numeric NOT NULL DEFAULT '0.00',
 "best_case" numeric NOT NULL DEFAULT '0.00',
 "worst_case" numeric NOT NULL DEFAULT '0.00',
 "committed" numeric NOT NULL DEFAULT '0.00',
 "deal_count" int NOT NULL DEFAULT '0',
 "won_value" numeric NOT NULL DEFAULT '0.00',
 "won_count" int NOT NULL DEFAULT '0',
 "by_stage" text DEFAULT NULL,
 "by_user" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_forecast_snapshots_crm_forecast_tenant_date_idx" ON "crm_forecast_snapshots" ("tenant_id","snapshot_date");
CREATE INDEX "crm_forecast_snapshots_tenant_id_idx" ON "crm_forecast_snapshots" ("tenant_id");

CREATE TABLE "crm_funnel_automations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "pipeline_id" integer DEFAULT NULL,
 "stage_id" integer DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "trigger_event" varchar(255) NOT NULL,
 "conditions" text DEFAULT NULL,
 "actions" text NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_funnel_automations_pipeline_id_foreign" ON "crm_funnel_automations" ("pipeline_id");
CREATE INDEX "crm_funnel_automations_stage_id_foreign" ON "crm_funnel_automations" ("stage_id");
CREATE INDEX "crm_funnel_automations_created_by_foreign" ON "crm_funnel_automations" ("created_by");
CREATE INDEX "crm_funnel_automations_tenant_id_index" ON "crm_funnel_automations" ("tenant_id");
CREATE INDEX "crm_funnel_automations_deleted_at_idx" ON "crm_funnel_automations" ("deleted_at");

CREATE TABLE "crm_interactive_proposals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "quote_id" integer NOT NULL,
 "deal_id" integer DEFAULT NULL,
 "token" varchar(64) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'sent',
 "view_count" int NOT NULL DEFAULT '0',
 "time_spent_seconds" int NOT NULL DEFAULT '0',
 "item_interactions" text DEFAULT NULL,
 "client_notes" text,
 "client_signature" varchar(255) DEFAULT NULL,
 "first_viewed_at" datetime NULL DEFAULT NULL,
 "last_viewed_at" datetime NULL DEFAULT NULL,
 "accepted_at" datetime NULL DEFAULT NULL,
 "rejected_at" datetime NULL DEFAULT NULL,
 "expires_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crm_interactive_proposals_token_unique" ON "crm_interactive_proposals" ("token");
CREATE INDEX "crm_interactive_proposals_quote_id_foreign" ON "crm_interactive_proposals" ("quote_id");
CREATE INDEX "crm_interactive_proposals_deal_id_foreign" ON "crm_interactive_proposals" ("deal_id");
CREATE INDEX "crm_interactive_proposals_tid_idx" ON "crm_interactive_proposals" ("tenant_id");
CREATE INDEX "crm_interactive_proposals_tenant_id_idx" ON "crm_interactive_proposals" ("tenant_id");

CREATE TABLE "crm_lead_scores" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "total_score" int NOT NULL DEFAULT '0',
 "score_breakdown" text DEFAULT NULL,
 "grade" varchar(255) DEFAULT NULL,
 "calculated_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crm_lead_scores_tenant_id_customer_id_unique" ON "crm_lead_scores" ("tenant_id","customer_id");
CREATE INDEX "crm_lead_scores_customer_id_foreign" ON "crm_lead_scores" ("customer_id");

CREATE TABLE "crm_lead_scoring_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "field" varchar(255) NOT NULL,
 "operator" varchar(255) NOT NULL,
 "value" varchar(255) NOT NULL,
 "points" int NOT NULL,
 "category" varchar(255) NOT NULL DEFAULT 'demographic',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_lead_scoring_rules_tenant_id_is_active_index" ON "crm_lead_scoring_rules" ("tenant_id","is_active");
CREATE INDEX "crm_lead_scoring_rules_tenant_id_idx" ON "crm_lead_scoring_rules" ("tenant_id");

CREATE TABLE "crm_loss_reasons" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "category" varchar(255) NOT NULL DEFAULT 'other',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_loss_reasons_tenant_id_is_active_index" ON "crm_loss_reasons" ("tenant_id","is_active");
CREATE INDEX "crm_loss_reasons_tenant_id_idx" ON "crm_loss_reasons" ("tenant_id");

CREATE TABLE "crm_message_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "channel" varchar NOT NULL,
 "subject" varchar(255) DEFAULT NULL,
 "body" text NOT NULL,
 "variables" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crm_message_templates_tenant_id_slug_unique" ON "crm_message_templates" ("tenant_id","slug");
CREATE INDEX "crm_message_templates_slug_index" ON "crm_message_templates" ("slug");

CREATE TABLE "crm_messages" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "deal_id" integer DEFAULT NULL,
 "user_id" integer DEFAULT NULL,
 "channel" varchar NOT NULL,
 "direction" varchar NOT NULL,
 "status" varchar NOT NULL DEFAULT 'pending',
 "subject" varchar(255) DEFAULT NULL,
 "body" text NOT NULL,
 "from_address" varchar(255) DEFAULT NULL,
 "to_address" varchar(255) DEFAULT NULL,
 "external_id" varchar(255) DEFAULT NULL,
 "provider" varchar(255) DEFAULT NULL,
 "attachments" text DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "sent_at" datetime NULL DEFAULT NULL,
 "delivered_at" datetime NULL DEFAULT NULL,
 "read_at" datetime NULL DEFAULT NULL,
 "failed_at" datetime NULL DEFAULT NULL,
 "error_message" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_messages_customer_id_foreign" ON "crm_messages" ("customer_id");
CREATE INDEX "crm_messages_deal_id_foreign" ON "crm_messages" ("deal_id");
CREATE INDEX "crm_messages_tenant_id_customer_id_channel_index" ON "crm_messages" ("tenant_id","customer_id","channel");
CREATE INDEX "crm_messages_tenant_id_channel_status_index" ON "crm_messages" ("tenant_id","channel","status");
CREATE INDEX "crm_messages_external_id_index" ON "crm_messages" ("external_id");
CREATE INDEX "crm_messages_crm_msg_tenant_deal_created" ON "crm_messages" ("tenant_id","deal_id","created_at");
CREATE INDEX "crm_messages_user_id_fk_idx" ON "crm_messages" ("user_id");
CREATE INDEX "crm_messages_tenant_id_idx" ON "crm_messages" ("tenant_id");

CREATE TABLE "crm_pipeline_stages" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "pipeline_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "color" varchar(255) DEFAULT NULL,
 "sort_order" int NOT NULL DEFAULT '0',
 "probability" int NOT NULL DEFAULT '0',
 "is_won" tinyint NOT NULL DEFAULT '0',
 "is_lost" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "order" int DEFAULT NULL
);
CREATE INDEX "crm_pipeline_stages_tenant_id_index" ON "crm_pipeline_stages" ("tenant_id");
CREATE INDEX "crm_pipeline_stages_pipeline_id_fk_idx" ON "crm_pipeline_stages" ("pipeline_id");
CREATE INDEX "crm_pipeline_stages_tenant_id_idx" ON "crm_pipeline_stages" ("tenant_id");

CREATE TABLE "crm_pipelines" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "color" varchar(255) DEFAULT NULL,
 "is_default" tinyint NOT NULL DEFAULT '0',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crm_pipelines_tenant_id_slug_unique" ON "crm_pipelines" ("tenant_id","slug");
CREATE INDEX "crm_pipelines_deleted_at_idx" ON "crm_pipelines" ("deleted_at");

CREATE TABLE "crm_referrals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "referrer_customer_id" integer NOT NULL,
 "referred_customer_id" integer DEFAULT NULL,
 "deal_id" integer DEFAULT NULL,
 "referred_name" varchar(255) NOT NULL,
 "referred_email" varchar(255) DEFAULT NULL,
 "referred_phone" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "reward_type" varchar(255) DEFAULT NULL,
 "reward_value" numeric DEFAULT NULL,
 "reward_given" tinyint NOT NULL DEFAULT '0',
 "converted_at" datetime NULL DEFAULT NULL,
 "reward_given_at" datetime NULL DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_referrals_referred_customer_id_foreign" ON "crm_referrals" ("referred_customer_id");
CREATE INDEX "crm_referrals_deal_id_foreign" ON "crm_referrals" ("deal_id");
CREATE INDEX "crm_referrals_tenant_id_status_index" ON "crm_referrals" ("tenant_id","status");
CREATE INDEX "crm_referrals_referrer_customer_id_index" ON "crm_referrals" ("referrer_customer_id");
CREATE INDEX "crm_referrals_tenant_id_idx" ON "crm_referrals" ("tenant_id");

CREATE TABLE "crm_sales_goals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "territory_id" integer DEFAULT NULL,
 "period_type" varchar(255) NOT NULL DEFAULT 'monthly',
 "period_start" date NOT NULL,
 "period_end" date NOT NULL,
 "target_revenue" numeric NOT NULL DEFAULT '0.00',
 "target_deals" int NOT NULL DEFAULT '0',
 "target_new_customers" int NOT NULL DEFAULT '0',
 "target_activities" int NOT NULL DEFAULT '0',
 "achieved_revenue" numeric NOT NULL DEFAULT '0.00',
 "achieved_deals" int NOT NULL DEFAULT '0',
 "achieved_new_customers" int NOT NULL DEFAULT '0',
 "achieved_activities" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_sales_goals_user_id_foreign" ON "crm_sales_goals" ("user_id");
CREATE INDEX "crm_sales_goals_territory_id_foreign" ON "crm_sales_goals" ("territory_id");
CREATE INDEX "crm_sales_goals_crm_goals_tenant_user_period_idx" ON "crm_sales_goals" ("tenant_id","user_id","period_start");
CREATE INDEX "crm_sales_goals_tenant_id_idx" ON "crm_sales_goals" ("tenant_id");

CREATE TABLE "crm_sequence_enrollments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "sequence_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "deal_id" integer DEFAULT NULL,
 "current_step" int NOT NULL DEFAULT '0',
 "status" varchar(255) NOT NULL DEFAULT 'active',
 "next_action_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "paused_at" datetime NULL DEFAULT NULL,
 "pause_reason" varchar(255) DEFAULT NULL,
 "enrolled_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_sequence_enrollments_sequence_id_foreign" ON "crm_sequence_enrollments" ("sequence_id");
CREATE INDEX "crm_sequence_enrollments_customer_id_foreign" ON "crm_sequence_enrollments" ("customer_id");
CREATE INDEX "crm_sequence_enrollments_deal_id_foreign" ON "crm_sequence_enrollments" ("deal_id");
CREATE INDEX "crm_sequence_enrollments_enrolled_by_foreign" ON "crm_sequence_enrollments" ("enrolled_by");
CREATE INDEX "crm_sequence_enrollments_crm_seq_enr_tenant_status_next_idx" ON "crm_sequence_enrollments" ("tenant_id","status","next_action_at");
CREATE INDEX "crm_sequence_enrollments_tenant_id_idx" ON "crm_sequence_enrollments" ("tenant_id");

CREATE TABLE "crm_sequence_steps" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL DEFAULT '0',
 "sequence_id" integer NOT NULL,
 "step_order" int NOT NULL,
 "delay_days" int NOT NULL DEFAULT '0',
 "channel" varchar(255) NOT NULL,
 "action_type" varchar(255) NOT NULL,
 "template_id" integer DEFAULT NULL,
 "subject" varchar(255) DEFAULT NULL,
 "body" text,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_sequence_steps_template_id_foreign" ON "crm_sequence_steps" ("template_id");
CREATE INDEX "crm_sequence_steps_sequence_id_step_order_index" ON "crm_sequence_steps" ("sequence_id","step_order");
CREATE INDEX "crm_sequence_steps_tenant_id_index" ON "crm_sequence_steps" ("tenant_id");

CREATE TABLE "crm_sequences" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "status" varchar(255) NOT NULL DEFAULT 'active',
 "total_steps" int NOT NULL DEFAULT '0',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_sequences_created_by_foreign" ON "crm_sequences" ("created_by");
CREATE INDEX "crm_sequences_tenant_id_status_index" ON "crm_sequences" ("tenant_id","status");
CREATE INDEX "crm_sequences_del_idx" ON "crm_sequences" ("deleted_at");
CREATE INDEX "crm_sequences_deleted_at_idx" ON "crm_sequences" ("deleted_at");
CREATE INDEX "crm_sequences_tenant_id_idx" ON "crm_sequences" ("tenant_id");

CREATE TABLE "crm_smart_alerts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "priority" varchar(255) NOT NULL DEFAULT 'medium',
 "title" varchar(255) NOT NULL,
 "description" text,
 "customer_id" integer DEFAULT NULL,
 "deal_id" integer DEFAULT NULL,
 "equipment_id" integer DEFAULT NULL,
 "assigned_to" integer DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "metadata" text DEFAULT NULL,
 "acknowledged_at" datetime NULL DEFAULT NULL,
 "resolved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_smart_alerts_customer_id_foreign" ON "crm_smart_alerts" ("customer_id");
CREATE INDEX "crm_smart_alerts_deal_id_foreign" ON "crm_smart_alerts" ("deal_id");
CREATE INDEX "crm_smart_alerts_equipment_id_foreign" ON "crm_smart_alerts" ("equipment_id");
CREATE INDEX "crm_smart_alerts_assigned_to_foreign" ON "crm_smart_alerts" ("assigned_to");
CREATE INDEX "crm_smart_alerts_crm_alerts_tenant_status_pri_idx" ON "crm_smart_alerts" ("tenant_id","status","priority");
CREATE INDEX "crm_smart_alerts_crm_alerts_tenant_type_idx" ON "crm_smart_alerts" ("tenant_id","type");
CREATE INDEX "crm_smart_alerts_tenant_id_idx" ON "crm_smart_alerts" ("tenant_id");

CREATE TABLE "crm_territories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "regions" text DEFAULT NULL,
 "zip_code_ranges" text DEFAULT NULL,
 "manager_id" integer DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_territories_manager_id_foreign" ON "crm_territories" ("manager_id");
CREATE INDEX "crm_territories_tenant_id_is_active_index" ON "crm_territories" ("tenant_id","is_active");
CREATE INDEX "crm_territories_del_idx" ON "crm_territories" ("deleted_at");
CREATE INDEX "crm_territories_tenant_id_idx" ON "crm_territories" ("tenant_id");
CREATE INDEX "crm_territories_deleted_at_idx" ON "crm_territories" ("deleted_at");

CREATE TABLE "crm_territory_members" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL DEFAULT '0',
 "territory_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "role" varchar(255) NOT NULL DEFAULT 'member',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crm_territory_members_territory_id_user_id_unique" ON "crm_territory_members" ("territory_id","user_id");
CREATE INDEX "crm_territory_members_user_id_foreign" ON "crm_territory_members" ("user_id");
CREATE INDEX "crm_territory_members_tenant_id_index" ON "crm_territory_members" ("tenant_id");

CREATE TABLE "crm_tracking_events" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "trackable_type" varchar(255) NOT NULL,
 "trackable_id" integer NOT NULL,
 "customer_id" integer DEFAULT NULL,
 "deal_id" integer DEFAULT NULL,
 "event_type" varchar(255) NOT NULL,
 "ip_address" varchar(255) DEFAULT NULL,
 "user_agent" varchar(255) DEFAULT NULL,
 "location" varchar(255) DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_tracking_events_customer_id_foreign" ON "crm_tracking_events" ("customer_id");
CREATE INDEX "crm_tracking_events_deal_id_foreign" ON "crm_tracking_events" ("deal_id");
CREATE INDEX "crm_tracking_events_trackable_type_trackable_id_index" ON "crm_tracking_events" ("trackable_type","trackable_id");
CREATE INDEX "crm_tracking_events_tenant_id_event_type_index" ON "crm_tracking_events" ("tenant_id","event_type");
CREATE INDEX "crm_tracking_events_tenant_id_idx" ON "crm_tracking_events" ("tenant_id");

CREATE TABLE "crm_web_form_submissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "form_id" integer NOT NULL,
 "customer_id" integer DEFAULT NULL,
 "deal_id" integer DEFAULT NULL,
 "data" text NOT NULL,
 "ip_address" varchar(255) DEFAULT NULL,
 "user_agent" varchar(255) DEFAULT NULL,
 "utm_source" varchar(255) DEFAULT NULL,
 "utm_medium" varchar(255) DEFAULT NULL,
 "utm_campaign" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "crm_web_form_submissions_form_id_index" ON "crm_web_form_submissions" ("form_id");
CREATE INDEX "crm_web_form_submissions_customer_id_fk_idx" ON "crm_web_form_submissions" ("customer_id");
CREATE INDEX "crm_web_form_submissions_deal_id_fk_idx" ON "crm_web_form_submissions" ("deal_id");
CREATE INDEX "crm_web_form_submissions_tenant_id_index" ON "crm_web_form_submissions" ("tenant_id");

CREATE TABLE "crm_web_forms" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" text,
 "fields" text NOT NULL,
 "pipeline_id" integer DEFAULT NULL,
 "assign_to" integer DEFAULT NULL,
 "sequence_id" integer DEFAULT NULL,
 "redirect_url" varchar(255) DEFAULT NULL,
 "success_message" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "submissions_count" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crm_web_forms_tenant_slug_unique" ON "crm_web_forms" ("tenant_id","slug");
CREATE INDEX "crm_web_forms_pipeline_id_foreign" ON "crm_web_forms" ("pipeline_id");
CREATE INDEX "crm_web_forms_assign_to_foreign" ON "crm_web_forms" ("assign_to");
CREATE INDEX "crm_web_forms_sequence_id_foreign" ON "crm_web_forms" ("sequence_id");
CREATE INDEX "crm_web_forms_del_idx" ON "crm_web_forms" ("deleted_at");
CREATE INDEX "crm_web_forms_deleted_at_idx" ON "crm_web_forms" ("deleted_at");

CREATE TABLE "custom_themes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "primary_color" varchar(7) NOT NULL DEFAULT '#3B82F6',
 "secondary_color" varchar(7) NOT NULL DEFAULT '#10B981',
 "accent_color" varchar(7) NOT NULL DEFAULT '#F59E0B',
 "dark_mode" tinyint NOT NULL DEFAULT '0',
 "sidebar_style" varchar(20) NOT NULL DEFAULT 'default',
 "font_family" varchar(50) NOT NULL DEFAULT 'Inter',
 "logo_url" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "custom_themes_tenant_id_unique" ON "custom_themes" ("tenant_id");

CREATE TABLE "customer_addresses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "type" varchar(30) DEFAULT NULL,
 "street" varchar(255) DEFAULT NULL,
 "number" varchar(20) DEFAULT NULL,
 "complement" varchar(255) DEFAULT NULL,
 "district" varchar(255) DEFAULT NULL,
 "city" varchar(255) DEFAULT NULL,
 "state" varchar(2) DEFAULT NULL,
 "zip" varchar(10) DEFAULT NULL,
 "country" varchar(5) NOT NULL DEFAULT 'BR',
 "is_main" tinyint NOT NULL DEFAULT '0',
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "customer_addresses_cust_addr_tenant_cust_idx" ON "customer_addresses" ("tenant_id","customer_id");
CREATE INDEX "customer_addresses_tenant_id_idx" ON "customer_addresses" ("tenant_id");

CREATE TABLE "customer_company_sizes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "customer_company_sizes_tid_slug_uq" ON "customer_company_sizes" ("tenant_id","slug");
CREATE INDEX "customer_company_sizes_tid_idx" ON "customer_company_sizes" ("tenant_id");
CREATE INDEX "customer_company_sizes_del_idx" ON "customer_company_sizes" ("deleted_at");
CREATE INDEX "customer_company_sizes_deleted_at_idx" ON "customer_company_sizes" ("deleted_at");

CREATE TABLE "customer_complaints" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "equipment_id" integer DEFAULT NULL,
 "description" text NOT NULL,
 "category" varchar(50) NOT NULL DEFAULT 'service',
 "severity" varchar(20) NOT NULL DEFAULT 'medium',
 "status" varchar(30) NOT NULL DEFAULT 'open',
 "resolution" text,
 "assigned_to" integer DEFAULT NULL,
 "resolved_at" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "response_due_at" date DEFAULT NULL,
 "responded_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "customer_complaints_equipment_id_foreign" ON "customer_complaints" ("equipment_id");
CREATE INDEX "customer_complaints_assigned_to_foreign" ON "customer_complaints" ("assigned_to");
CREATE INDEX "customer_complaints_tid_idx" ON "customer_complaints" ("tenant_id");
CREATE INDEX "customer_complaints_customer_id_fk_idx" ON "customer_complaints" ("customer_id");
CREATE INDEX "customer_complaints_work_order_id_fk_idx" ON "customer_complaints" ("work_order_id");
CREATE INDEX "customer_complaints_tenant_id_idx" ON "customer_complaints" ("tenant_id");

CREATE TABLE "customer_contacts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "customer_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "role" varchar(100) DEFAULT NULL,
 "phone" varchar(20) DEFAULT NULL,
 "email" varchar(255) DEFAULT NULL,
 "is_primary" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "customer_contacts_tenant_id_index" ON "customer_contacts" ("tenant_id");
CREATE INDEX "customer_contacts_customer_id_fk_idx" ON "customer_contacts" ("customer_id");
CREATE INDEX "customer_contacts_cc_cid_prim_idx" ON "customer_contacts" ("customer_id","is_primary");
CREATE INDEX "customer_contacts_tenant_id_idx" ON "customer_contacts" ("tenant_id");

CREATE TABLE "customer_documents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "type" varchar(50) NOT NULL DEFAULT 'other',
 "file_path" varchar(255) NOT NULL,
 "file_name" varchar(255) NOT NULL,
 "file_size" int DEFAULT NULL,
 "expiry_date" date DEFAULT NULL,
 "notes" text,
 "uploaded_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "customer_documents_uploaded_by_foreign" ON "customer_documents" ("uploaded_by");
CREATE INDEX "customer_documents_tid_idx" ON "customer_documents" ("tenant_id");
CREATE INDEX "customer_documents_customer_id_fk_idx" ON "customer_documents" ("customer_id");
CREATE INDEX "customer_documents_tenant_id_idx" ON "customer_documents" ("tenant_id");

CREATE TABLE "customer_health_scores" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "health_index" int NOT NULL DEFAULT '100',
 "risk_level" varchar(255) NOT NULL DEFAULT 'low',
 "factors" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "customer_health_scores_tenant_id_customer_id_unique" ON "customer_health_scores" ("tenant_id","customer_id");
CREATE INDEX "customer_health_scores_customer_id_foreign" ON "customer_health_scores" ("customer_id");

CREATE TABLE "customer_locations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "address" varchar(500) NOT NULL,
 "city" varchar(100) NOT NULL,
 "state" varchar(2) NOT NULL,
 "zip_code" varchar(10) DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "contact_name" varchar(255) DEFAULT NULL,
 "contact_phone" varchar(20) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "customer_locations_tenant_id_index" ON "customer_locations" ("tenant_id");
CREATE INDEX "customer_locations_customer_id_index" ON "customer_locations" ("customer_id");
CREATE INDEX "customer_locations_tenant_id_idx" ON "customer_locations" ("tenant_id");

CREATE TABLE "customer_ratings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "customer_ratings_tid_slug_uq" ON "customer_ratings" ("tenant_id","slug");
CREATE INDEX "customer_ratings_tid_idx" ON "customer_ratings" ("tenant_id");
CREATE INDEX "customer_ratings_del_idx" ON "customer_ratings" ("deleted_at");
CREATE INDEX "customer_ratings_deleted_at_idx" ON "customer_ratings" ("deleted_at");

CREATE TABLE "customer_rfm_scores" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "recency_score" int NOT NULL,
 "frequency_score" int NOT NULL,
 "monetary_score" int NOT NULL,
 "rfm_segment" varchar(255) NOT NULL,
 "total_score" int NOT NULL,
 "last_purchase_date" date DEFAULT NULL,
 "purchase_count" int NOT NULL DEFAULT '0',
 "total_revenue" numeric NOT NULL DEFAULT '0.00',
 "calculated_at" datetime NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "crfm_tenant_cust_uniq" ON "customer_rfm_scores" ("tenant_id","customer_id");
CREATE INDEX "customer_rfm_scores_customer_id_foreign" ON "customer_rfm_scores" ("customer_id");
CREATE INDEX "customer_rfm_scores_crfm_tenant_seg_idx" ON "customer_rfm_scores" ("tenant_id","rfm_segment");

CREATE TABLE "customer_segments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "customer_segments_tid_slug_uq" ON "customer_segments" ("tenant_id","slug");
CREATE INDEX "customer_segments_tid_idx" ON "customer_segments" ("tenant_id");
CREATE INDEX "customer_segments_del_idx" ON "customer_segments" ("deleted_at");
CREATE INDEX "customer_segments_deleted_at_idx" ON "customer_segments" ("deleted_at");

CREATE TABLE "customers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar NOT NULL DEFAULT 'PF',
 "name" varchar(255) NOT NULL,
 "document" text,
 "document_hash" varchar(64) DEFAULT NULL,
 "asaas_id" varchar(255) DEFAULT NULL,
 "email" varchar(255) DEFAULT NULL,
 "phone" varchar(20) DEFAULT NULL,
 "notification_preferences" text DEFAULT NULL,
 "phone2" varchar(20) DEFAULT NULL,
 "address_zip" varchar(10) DEFAULT NULL,
 "address_street" varchar(255) DEFAULT NULL,
 "address_number" varchar(20) DEFAULT NULL,
 "address_complement" varchar(100) DEFAULT NULL,
 "address_neighborhood" varchar(100) DEFAULT NULL,
 "address_city" varchar(100) DEFAULT NULL,
 "address_state" varchar(2) DEFAULT NULL,
 "notes" text,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "source" varchar(255) DEFAULT NULL,
 "segment" varchar(50) DEFAULT NULL,
 "company_size" varchar(255) DEFAULT NULL,
 "annual_revenue_estimate" numeric DEFAULT NULL,
 "contract_type" varchar(255) DEFAULT NULL,
 "contract_start" date DEFAULT NULL,
 "contract_end" date DEFAULT NULL,
 "health_score" int NOT NULL DEFAULT '0',
 "last_contact_at" datetime NULL DEFAULT NULL,
 "next_follow_up_at" datetime NULL DEFAULT NULL,
 "assigned_seller_id" integer DEFAULT NULL,
 "tags" text DEFAULT NULL,
 "rating" varchar(255) DEFAULT NULL,
 "trade_name" varchar(255) DEFAULT NULL,
 "google_maps_link" text,
 "abc_classification" varchar(1) DEFAULT NULL,
 "credit_limit" numeric DEFAULT NULL,
 "nps_score" numeric DEFAULT NULL,
 "ltv_total" numeric NOT NULL DEFAULT '0.00',
 "churn_risk" varchar(20) DEFAULT NULL,
 "referred_by_customer_id" integer DEFAULT NULL,
 "loyalty_points" int NOT NULL DEFAULT '0',
 "first_service_date" date DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "satisfaction_score" numeric DEFAULT NULL,
 "last_survey_at" datetime NULL DEFAULT NULL,
 "territory_id" integer DEFAULT NULL,
 "lead_score" int NOT NULL DEFAULT '0',
 "lead_grade" varchar(255) DEFAULT NULL,
 "state_registration" varchar(30) DEFAULT NULL,
 "municipal_registration" varchar(30) DEFAULT NULL,
 "cnae_code" varchar(10) DEFAULT NULL,
 "cnae_description" varchar(255) DEFAULT NULL,
 "legal_nature" varchar(255) DEFAULT NULL,
 "capital" numeric DEFAULT NULL,
 "simples_nacional" tinyint DEFAULT NULL,
 "mei" tinyint DEFAULT NULL,
 "company_status" varchar(255) DEFAULT NULL,
 "opened_at" date DEFAULT NULL,
 "is_rural_producer" tinyint NOT NULL DEFAULT '0',
 "partners" text DEFAULT NULL,
 "secondary_activities" text DEFAULT NULL,
 "enrichment_data" text DEFAULT NULL,
 "enriched_at" datetime NULL DEFAULT NULL,
 "company_name" varchar(255) DEFAULT NULL,
 "document_hash_active_key" char(19) GENERATED ALWAYS AS (ifnull(cast("deleted_at" as char(19)),'1970-01-01 00:00:00')) STORED
);
CREATE UNIQUE INDEX "customers_tenant_active_document_hash_unique" ON "customers" ("tenant_id","document_hash","document_hash_active_key");
CREATE INDEX "customers_tenant_id_name_index" ON "customers" ("tenant_id","name");
CREATE INDEX "customers_assigned_seller_id_foreign" ON "customers" ("assigned_seller_id");
CREATE INDEX "customers_referred_by_customer_id_foreign" ON "customers" ("referred_by_customer_id");
CREATE INDEX "customers_territory_id_foreign" ON "customers" ("territory_id");
CREATE INDEX "customers_cust_tenant_active" ON "customers" ("tenant_id","is_active");
CREATE INDEX "customers_cust_tenant_seller" ON "customers" ("tenant_id","assigned_seller_id");
CREATE INDEX "customers_cust_tenant_contact" ON "customers" ("tenant_id","last_contact_at");
CREATE INDEX "customers_cust_tenant_followup" ON "customers" ("tenant_id","next_follow_up_at");
CREATE INDEX "customers_cust_tenant_health" ON "customers" ("tenant_id","health_score");
CREATE INDEX "customers_cust_deleted_at" ON "customers" ("tenant_id","deleted_at");
CREATE INDEX "customers_del_idx" ON "customers" ("deleted_at");
CREATE INDEX "customers_asaas_id_index" ON "customers" ("asaas_id");
CREATE INDEX "customers_tenant_document_hash_idx" ON "customers" ("tenant_id","document_hash");
CREATE INDEX "customers_deleted_at_idx" ON "customers" ("deleted_at");

CREATE TABLE "data_export_jobs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "analytics_dataset_id" integer NOT NULL,
 "created_by" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "source_modules" text DEFAULT NULL,
 "filters" text DEFAULT NULL,
 "output_format" varchar(10) NOT NULL,
 "output_path" varchar(255) DEFAULT NULL,
 "file_size_bytes" integer DEFAULT NULL,
 "rows_exported" int DEFAULT NULL,
 "started_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "error_message" text,
 "scheduled_cron" varchar(100) DEFAULT NULL,
 "last_scheduled_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "data_export_jobs_analytics_dataset_id_foreign" ON "data_export_jobs" ("analytics_dataset_id");
CREATE INDEX "data_export_jobs_created_by_foreign" ON "data_export_jobs" ("created_by");
CREATE INDEX "data_export_jobs_tenant_status_idx" ON "data_export_jobs" ("tenant_id","status");
CREATE INDEX "data_export_jobs_tenant_id_idx" ON "data_export_jobs" ("tenant_id");

CREATE TABLE "data_masking_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "table_name" varchar(100) NOT NULL,
 "column_name" varchar(100) NOT NULL,
 "masking_type" varchar(20) NOT NULL,
 "roles_exempt" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NOT NULL
);
CREATE INDEX "data_masking_rules_tenant_id_index" ON "data_masking_rules" ("tenant_id");
CREATE INDEX "data_masking_rules_tenant_id_idx" ON "data_masking_rules" ("tenant_id");

CREATE TABLE "debt_renegotiation_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "debt_renegotiation_id" integer NOT NULL,
 "account_receivable_id" integer NOT NULL,
 "original_amount" numeric NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "debt_renegotiation_items_dri_ar" ON "debt_renegotiation_items" ("account_receivable_id");
CREATE INDEX "debt_renegotiation_items_debt_renegotiation_i_fk_idx" ON "debt_renegotiation_items" ("debt_renegotiation_id");
CREATE INDEX "debt_renegotiation_items_account_receivable_i_fk_idx" ON "debt_renegotiation_items" ("account_receivable_id");
CREATE INDEX "debt_renegotiation_items_tenant_id_idx" ON "debt_renegotiation_items" ("tenant_id");

CREATE TABLE "debt_renegotiations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "original_total" numeric NOT NULL,
 "negotiated_total" numeric NOT NULL,
 "discount_amount" numeric NOT NULL DEFAULT '0.00',
 "interest_amount" numeric NOT NULL DEFAULT '0.00',
 "fine_amount" numeric NOT NULL DEFAULT '0.00',
 "new_installments" int NOT NULL DEFAULT '1',
 "first_due_date" date NOT NULL,
 "notes" text,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "created_by" integer NOT NULL,
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "description" varchar(255) DEFAULT NULL
);
CREATE INDEX "debt_renegotiations_created_by_foreign" ON "debt_renegotiations" ("created_by");
CREATE INDEX "debt_renegotiations_approved_by_foreign" ON "debt_renegotiations" ("approved_by");
CREATE INDEX "debt_renegotiations_tid_idx" ON "debt_renegotiations" ("tenant_id");
CREATE INDEX "debt_renegotiations_customer_id_fk_idx" ON "debt_renegotiations" ("customer_id");
CREATE INDEX "debt_renegotiations_tid_st_idx" ON "debt_renegotiations" ("tenant_id","status");
CREATE INDEX "debt_renegotiations_tenant_id_idx" ON "debt_renegotiations" ("tenant_id");

CREATE TABLE "departments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "parent_id" integer DEFAULT NULL,
 "manager_id" integer DEFAULT NULL,
 "cost_center" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "departments_parent_id_foreign" ON "departments" ("parent_id");
CREATE INDEX "departments_manager_id_foreign" ON "departments" ("manager_id");
CREATE INDEX "departments_tid_idx" ON "departments" ("tenant_id");
CREATE INDEX "departments_tenant_id_idx" ON "departments" ("tenant_id");

CREATE TABLE "depreciation_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "asset_record_id" integer NOT NULL,
 "reference_month" date NOT NULL,
 "depreciation_amount" numeric NOT NULL,
 "accumulated_before" numeric NOT NULL,
 "accumulated_after" numeric NOT NULL,
 "book_value_after" numeric NOT NULL,
 "method_used" varchar(30) NOT NULL,
 "ciap_installment_number" int DEFAULT NULL,
 "ciap_credit_value" numeric DEFAULT NULL,
 "generated_by" varchar(20) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "depreciation_logs_tenant_asset_month_unique" ON "depreciation_logs" ("tenant_id","asset_record_id","reference_month");
CREATE INDEX "depreciation_logs_asset_record_id_foreign" ON "depreciation_logs" ("asset_record_id");
CREATE INDEX "depreciation_logs_tenant_month_idx" ON "depreciation_logs" ("tenant_id","reference_month");

CREATE TABLE "document_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "document_types_tid_slug_uq" ON "document_types" ("tenant_id","slug");
CREATE INDEX "document_types_tid_idx" ON "document_types" ("tenant_id");
CREATE INDEX "document_types_del_idx" ON "document_types" ("deleted_at");
CREATE INDEX "document_types_deleted_at_idx" ON "document_types" ("deleted_at");

CREATE TABLE "document_versions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "document_code" varchar(255) NOT NULL,
 "title" varchar(255) NOT NULL,
 "category" varchar(255) NOT NULL,
 "version" varchar(20) NOT NULL,
 "description" text,
 "file_path" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'draft',
 "created_by" integer NOT NULL,
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "effective_date" date DEFAULT NULL,
 "review_date" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "document_versions_created_by_foreign" ON "document_versions" ("created_by");
CREATE INDEX "document_versions_approved_by_foreign" ON "document_versions" ("approved_by");
CREATE INDEX "document_versions_tenant_id_document_code_index" ON "document_versions" ("tenant_id","document_code");
CREATE INDEX "document_versions_del_idx" ON "document_versions" ("deleted_at");
CREATE INDEX "document_versions_tenant_id_idx" ON "document_versions" ("tenant_id");
CREATE INDEX "document_versions_deleted_at_idx" ON "document_versions" ("deleted_at");

CREATE TABLE "ecological_disposals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "disposal_method" varchar(255) NOT NULL,
 "disposal_company" varchar(255) DEFAULT NULL,
 "certificate_number" varchar(100) DEFAULT NULL,
 "reason" varchar(500) NOT NULL,
 "notes" text,
 "disposed_by" integer DEFAULT NULL,
 "disposed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "created_by" integer DEFAULT NULL
);
CREATE INDEX "ecological_disposals_product_id_foreign" ON "ecological_disposals" ("product_id");
CREATE INDEX "ecological_disposals_created_by_foreign" ON "ecological_disposals" ("created_by");
CREATE INDEX "ecological_disposals_tid_idx" ON "ecological_disposals" ("tenant_id");
CREATE INDEX "ecological_disposals_tenant_id_idx" ON "ecological_disposals" ("tenant_id");

CREATE TABLE "email_accounts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "label" varchar(255) NOT NULL,
 "email_address" varchar(255) NOT NULL,
 "imap_host" varchar(255) NOT NULL,
 "imap_port" integer NOT NULL DEFAULT '993',
 "imap_encryption" varchar(255) NOT NULL DEFAULT 'ssl',
 "imap_username" text NOT NULL,
 "imap_password" text NOT NULL,
 "smtp_host" varchar(255) DEFAULT NULL,
 "smtp_port" integer DEFAULT NULL,
 "smtp_encryption" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "last_sync_at" datetime NULL DEFAULT NULL,
 "last_sync_uid" integer DEFAULT NULL,
 "sync_status" varchar(255) NOT NULL DEFAULT 'idle',
 "sync_error" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "email_accounts_tenant_id_email_address_unique" ON "email_accounts" ("tenant_id","email_address");
CREATE INDEX "email_accounts_ea_tenant" ON "email_accounts" ("tenant_id");

CREATE TABLE "email_activities" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "email_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "type" varchar(255) NOT NULL,
 "details" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_activities_tid_idx" ON "email_activities" ("tenant_id");
CREATE INDEX "email_activities_email_id_fk_idx" ON "email_activities" ("email_id");
CREATE INDEX "email_activities_user_id_fk_idx" ON "email_activities" ("user_id");
CREATE INDEX "email_activities_tenant_id_idx" ON "email_activities" ("tenant_id");

CREATE TABLE "email_attachments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "email_id" integer NOT NULL,
 "filename" varchar(255) NOT NULL,
 "mime_type" varchar(255) NOT NULL,
 "size_bytes" int NOT NULL DEFAULT '0',
 "storage_path" varchar(255) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_attachments_ea_email" ON "email_attachments" ("email_id");

CREATE TABLE "email_campaigns" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "subject" varchar(255) NOT NULL,
 "content" text NOT NULL,
 "segment" varchar(255) NOT NULL DEFAULT 'all',
 "status" varchar(255) NOT NULL DEFAULT 'draft',
 "scheduled_at" datetime NULL DEFAULT NULL,
 "sent_at" datetime NULL DEFAULT NULL,
 "sent_count" int NOT NULL DEFAULT '0',
 "opened_count" int NOT NULL DEFAULT '0',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_campaigns_tenant_id_status_index" ON "email_campaigns" ("tenant_id","status");
CREATE INDEX "email_campaigns_tenant_id_idx" ON "email_campaigns" ("tenant_id");

CREATE TABLE "email_email_tag" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "email_id" integer NOT NULL,
 "email_tag_id" integer NOT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE INDEX "email_email_tag_email_id_foreign" ON "email_email_tag" ("email_id");
CREATE INDEX "email_email_tag_email_tag_id_foreign" ON "email_email_tag" ("email_tag_id");
CREATE INDEX "email_email_tag_tenant_idx" ON "email_email_tag" ("tenant_id");
CREATE INDEX "email_email_tag_tenant_id_idx" ON "email_email_tag" ("tenant_id");

CREATE TABLE "email_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "to" varchar(255) NOT NULL,
 "subject" varchar(255) NOT NULL,
 "body" text,
 "status" varchar(20) NOT NULL DEFAULT 'sent',
 "sent_at" datetime NULL DEFAULT NULL,
 "error" text,
 "related_type" varchar(255) DEFAULT NULL,
 "related_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_logs_tenant_status_idx" ON "email_logs" ("tenant_id","status");
CREATE INDEX "email_logs_tenant_id_idx" ON "email_logs" ("tenant_id");

CREATE TABLE "email_notes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "email_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "content" text NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_notes_email_id_foreign" ON "email_notes" ("email_id");
CREATE INDEX "email_notes_user_id_foreign" ON "email_notes" ("user_id");
CREATE INDEX "email_notes_tid_idx" ON "email_notes" ("tenant_id");
CREATE INDEX "email_notes_tenant_id_idx" ON "email_notes" ("tenant_id");

CREATE TABLE "email_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "priority" integer NOT NULL DEFAULT '10',
 "conditions" text NOT NULL,
 "actions" text NOT NULL,
 "description" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_rules_tenant_id_is_active_priority_index" ON "email_rules" ("tenant_id","is_active","priority");
CREATE INDEX "email_rules_tenant_id_idx" ON "email_rules" ("tenant_id");

CREATE TABLE "email_signatures" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "email_account_id" integer DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "html_content" text NOT NULL,
 "is_default" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_signatures_user_id_foreign" ON "email_signatures" ("user_id");
CREATE INDEX "email_signatures_email_account_id_foreign" ON "email_signatures" ("email_account_id");
CREATE INDEX "email_signatures_tid_idx" ON "email_signatures" ("tenant_id");
CREATE INDEX "email_signatures_tenant_id_idx" ON "email_signatures" ("tenant_id");

CREATE TABLE "email_tags" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "color" varchar(255) NOT NULL DEFAULT '#EF4444',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_tags_tid_idx" ON "email_tags" ("tenant_id");
CREATE INDEX "email_tags_tenant_id_idx" ON "email_tags" ("tenant_id");

CREATE TABLE "email_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "subject" varchar(255) DEFAULT NULL,
 "body" text NOT NULL,
 "is_shared" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "email_templates_user_id_foreign" ON "email_templates" ("user_id");
CREATE INDEX "email_templates_tid_idx" ON "email_templates" ("tenant_id");
CREATE INDEX "email_templates_tenant_id_idx" ON "email_templates" ("tenant_id");

CREATE TABLE "emails" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "email_account_id" integer NOT NULL,
 "message_id" varchar(255) NOT NULL,
 "in_reply_to" varchar(255) DEFAULT NULL,
 "thread_id" varchar(255) DEFAULT NULL,
 "folder" varchar(255) NOT NULL DEFAULT 'INBOX',
 "uid" integer DEFAULT NULL,
 "from_address" varchar(255) NOT NULL,
 "from_name" varchar(255) DEFAULT NULL,
 "to_addresses" text NOT NULL,
 "cc_addresses" text DEFAULT NULL,
 "subject" varchar(500) NOT NULL,
 "body_text" text,
 "body_html" text,
 "snippet" varchar(500) DEFAULT NULL,
 "date" datetime NOT NULL,
 "is_read" tinyint NOT NULL DEFAULT '0',
 "is_starred" tinyint NOT NULL DEFAULT '0',
 "is_archived" tinyint NOT NULL DEFAULT '0',
 "has_attachments" tinyint NOT NULL DEFAULT '0',
 "ai_category" varchar(255) DEFAULT NULL,
 "ai_summary" text,
 "ai_sentiment" varchar(20) DEFAULT NULL,
 "ai_priority" varchar(20) DEFAULT NULL,
 "ai_suggested_action" varchar(50) DEFAULT NULL,
 "ai_confidence" numeric DEFAULT NULL,
 "ai_classified_at" datetime NULL DEFAULT NULL,
 "customer_id" integer DEFAULT NULL,
 "linked_type" varchar(255) DEFAULT NULL,
 "linked_id" integer DEFAULT NULL,
 "direction" varchar(255) NOT NULL DEFAULT 'inbound',
 "status" varchar(255) NOT NULL DEFAULT 'new',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "scheduled_at" datetime NULL DEFAULT NULL,
 "sent_at" datetime NULL DEFAULT NULL,
 "tracking_id" varchar(255) DEFAULT NULL,
 "read_count" int NOT NULL DEFAULT '0',
 "last_read_at" datetime NULL DEFAULT NULL,
 "snoozed_until" datetime NULL DEFAULT NULL,
 "assigned_to_user_id" integer DEFAULT NULL,
 "assigned_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "emails_message_id_unique" ON "emails" ("message_id");
CREATE INDEX "emails_customer_id_foreign" ON "emails" ("customer_id");
CREATE INDEX "emails_linked_type_linked_id_index" ON "emails" ("linked_type","linked_id");
CREATE INDEX "emails_email_account_id_uid_index" ON "emails" ("email_account_id","uid");
CREATE INDEX "emails_tenant_id_status_index" ON "emails" ("tenant_id","status");
CREATE INDEX "emails_tenant_id_ai_category_index" ON "emails" ("tenant_id","ai_category");
CREATE INDEX "emails_in_reply_to_index" ON "emails" ("in_reply_to");
CREATE INDEX "emails_thread_id_index" ON "emails" ("thread_id");
CREATE INDEX "emails_date_index" ON "emails" ("date");
CREATE INDEX "emails_ai_category_index" ON "emails" ("ai_category");
CREATE INDEX "emails_assigned_to_user_id_foreign" ON "emails" ("assigned_to_user_id");
CREATE INDEX "emails_tracking_id_index" ON "emails" ("tracking_id");
CREATE INDEX "emails_em_tenant_folder_date" ON "emails" ("tenant_id","folder","date");
CREATE INDEX "emails_em_account" ON "emails" ("email_account_id");
CREATE INDEX "emails_tenant_id_idx" ON "emails" ("tenant_id");

CREATE TABLE "embedded_dashboards" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "provider" varchar(30) NOT NULL,
 "embed_url" text NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "display_order" int NOT NULL DEFAULT '0',
 "created_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "embedded_dashboards_created_by_foreign" ON "embedded_dashboards" ("created_by");
CREATE INDEX "embedded_dashboards_tenant_order_idx" ON "embedded_dashboards" ("tenant_id","display_order");
CREATE INDEX "embedded_dashboards_tenant_id_idx" ON "embedded_dashboards" ("tenant_id");

CREATE TABLE "employee_benefits" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "provider" varchar(255) DEFAULT NULL,
 "value" numeric NOT NULL,
 "employee_contribution" numeric NOT NULL DEFAULT '0.00',
 "start_date" date NOT NULL,
 "end_date" date DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "employee_benefits_tid_idx" ON "employee_benefits" ("tenant_id");
CREATE INDEX "employee_benefits_user_id_fk_idx" ON "employee_benefits" ("user_id");
CREATE INDEX "employee_benefits_tenant_id_idx" ON "employee_benefits" ("tenant_id");

CREATE TABLE "employee_dependents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "cpf" text,
 "cpf_hash" varchar(64) DEFAULT NULL,
 "birth_date" date DEFAULT NULL,
 "relationship" varchar(30) NOT NULL,
 "is_irrf_dependent" tinyint NOT NULL DEFAULT '1',
 "is_benefit_dependent" tinyint NOT NULL DEFAULT '0',
 "start_date" date DEFAULT NULL,
 "end_date" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "employee_dependents_user_id_foreign" ON "employee_dependents" ("user_id");
CREATE INDEX "employee_dependents_tenant_id_user_id_index" ON "employee_dependents" ("tenant_id","user_id");
CREATE INDEX "employee_dependents_tenant_cpf_hash_idx" ON "employee_dependents" ("tenant_id","cpf_hash");

CREATE TABLE "employee_documents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "category" varchar(50) NOT NULL,
 "name" varchar(255) NOT NULL,
 "file_path" varchar(255) NOT NULL,
 "expiry_date" date DEFAULT NULL,
 "issued_date" date DEFAULT NULL,
 "issuer" varchar(255) DEFAULT NULL,
 "is_mandatory" tinyint NOT NULL DEFAULT '0',
 "status" varchar(30) NOT NULL DEFAULT 'valid',
 "notes" text,
 "uploaded_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "employee_documents_user_id_foreign" ON "employee_documents" ("user_id");
CREATE INDEX "employee_documents_uploaded_by_foreign" ON "employee_documents" ("uploaded_by");
CREATE INDEX "employee_documents_tid_idx" ON "employee_documents" ("tenant_id");
CREATE INDEX "employee_documents_tenant_id_idx" ON "employee_documents" ("tenant_id");

CREATE TABLE "epi_records" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "epi_type" varchar(100) NOT NULL,
 "ca_number" varchar(20) DEFAULT NULL,
 "delivered_at" date NOT NULL,
 "expiry_date" date DEFAULT NULL,
 "quantity" int NOT NULL DEFAULT '1',
 "status" varchar(20) NOT NULL DEFAULT 'active',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "epi_records_tenant_id_index" ON "epi_records" ("tenant_id");
CREATE INDEX "epi_records_user_id_index" ON "epi_records" ("user_id");
CREATE INDEX "epi_records_tenant_id_idx" ON "epi_records" ("tenant_id");

CREATE TABLE "equipment_brands" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "equipment_brands_tid_slug_uq" ON "equipment_brands" ("tenant_id","slug");
CREATE INDEX "equipment_brands_tid_idx" ON "equipment_brands" ("tenant_id");
CREATE INDEX "equipment_brands_del_idx" ON "equipment_brands" ("deleted_at");
CREATE INDEX "equipment_brands_deleted_at_idx" ON "equipment_brands" ("deleted_at");

CREATE TABLE "equipment_calibrations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "equipment_id" integer NOT NULL,
 "calibration_date" date NOT NULL,
 "calibration_started_at" datetime DEFAULT NULL,
 "calibration_completed_at" datetime DEFAULT NULL,
 "next_due_date" date DEFAULT NULL,
 "calibration_type" varchar(30) NOT NULL DEFAULT 'externa',
 "result" varchar(30) NOT NULL DEFAULT 'approved',
 "laboratory" varchar(150) DEFAULT NULL,
 "certificate_number" varchar(50) DEFAULT NULL,
 "certificate_file" varchar(255) DEFAULT NULL,
 "uncertainty" varchar(50) DEFAULT NULL,
 "errors_found" text DEFAULT NULL,
 "corrections_applied" text,
 "performed_by" integer DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "cost" numeric DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "certificate_pdf_path" varchar(255) DEFAULT NULL,
 "standard_used" varchar(255) DEFAULT NULL,
 "error_found" numeric DEFAULT NULL,
 "technician_notes" text,
 "temperature" numeric DEFAULT NULL,
 "humidity" numeric DEFAULT NULL,
 "pressure" numeric DEFAULT NULL,
 "status" varchar(20) DEFAULT NULL,
 "nominal_mass" varchar(255) DEFAULT NULL,
 "error_after_adjustment" numeric DEFAULT NULL,
 "traceability" text,
 "eccentricity_data" text DEFAULT NULL,
 "has_nonconformity" tinyint NOT NULL DEFAULT '0',
 "nonconformity_details" text,
 "batch_generated" tinyint NOT NULL DEFAULT '0',
 "verification_token" varchar(64) DEFAULT NULL,
 "certificate_template_id" integer DEFAULT NULL,
 "conformity_declaration" varchar(255) DEFAULT NULL,
 "max_permissible_error" numeric DEFAULT NULL,
 "max_error_found" numeric DEFAULT NULL,
 "mass_unit" varchar(10) NOT NULL DEFAULT 'kg',
 "calibration_method" varchar(255) DEFAULT NULL,
 "precision_class" varchar(10) DEFAULT NULL,
 "received_date" date DEFAULT NULL,
 "issued_date" date DEFAULT NULL,
 "calibration_location" varchar(500) DEFAULT NULL,
 "calibration_location_type" varchar(30) DEFAULT NULL,
 "before_adjustment_data" text DEFAULT NULL,
 "after_adjustment_data" text DEFAULT NULL,
 "condition_as_found" text,
 "condition_as_left" text,
 "adjustment_performed" tinyint NOT NULL DEFAULT '0',
 "verification_type" varchar(30) DEFAULT NULL,
 "verification_division_e" numeric DEFAULT NULL,
 "prefilled_from_id" integer DEFAULT NULL,
 "gravity_acceleration" numeric DEFAULT NULL,
 "decision_rule" varchar(30) DEFAULT 'simple',
 "uncertainty_budget" text DEFAULT NULL,
 "coverage_factor_k" numeric DEFAULT NULL,
 "confidence_level" numeric DEFAULT NULL,
 "guard_band_mode" varchar(20) DEFAULT NULL,
 "guard_band_value" numeric DEFAULT NULL,
 "producer_risk_alpha" numeric DEFAULT NULL,
 "consumer_risk_beta" numeric DEFAULT NULL,
 "decision_result" varchar(10) DEFAULT NULL,
 "decision_z_value" numeric DEFAULT NULL,
 "decision_false_accept_prob" numeric DEFAULT NULL,
 "decision_guard_band_applied" numeric DEFAULT NULL,
 "decision_calculated_at" datetime DEFAULT NULL,
 "decision_calculated_by" integer DEFAULT NULL,
 "decision_notes" text,
 "laboratory_address" varchar(500) DEFAULT NULL,
 "scope_declaration" text,
 "accreditation_scope_id" integer DEFAULT NULL,
 "icp_signature_status" varchar(255) DEFAULT NULL
);
CREATE UNIQUE INDEX "equipment_calibrations_verification_token_unique" ON "equipment_calibrations" ("verification_token");
CREATE INDEX "equipment_calibrations_performed_by_foreign" ON "equipment_calibrations" ("performed_by");
CREATE INDEX "equipment_calibrations_approved_by_foreign" ON "equipment_calibrations" ("approved_by");
CREATE INDEX "equipment_calibrations_equipment_id_calibration_date_index" ON "equipment_calibrations" ("equipment_id","calibration_date");
CREATE INDEX "equipment_calibrations_tenant_id_index" ON "equipment_calibrations" ("tenant_id");
CREATE INDEX "equipment_calibrations_prefilled_from_id_foreign" ON "equipment_calibrations" ("prefilled_from_id");
CREATE INDEX "equipment_calibrations_certificate_template_id_foreign" ON "equipment_calibrations" ("certificate_template_id");
CREATE INDEX "equipment_calibrations_eq_cal_tenant_status_idx" ON "equipment_calibrations" ("tenant_id","status");
CREATE INDEX "equipment_calibrations_eq_cal_tenant_equip_idx" ON "equipment_calibrations" ("tenant_id","equipment_id");
CREATE INDEX "equipment_calibrations_work_order_id_fk_idx" ON "equipment_calibrations" ("work_order_id");
CREATE INDEX "equipment_calibrations_decision_calculated_by_foreign" ON "equipment_calibrations" ("decision_calculated_by");
CREATE INDEX "equipment_calibrations_eq_cal_decision_result_idx" ON "equipment_calibrations" ("tenant_id","decision_result");
CREATE INDEX "equipment_calibrations_accreditation_scope_id_foreign" ON "equipment_calibrations" ("accreditation_scope_id");

CREATE TABLE "equipment_categories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "equipment_categories_tid_slug_uq" ON "equipment_categories" ("tenant_id","slug");
CREATE INDEX "equipment_categories_tid_idx" ON "equipment_categories" ("tenant_id");
CREATE INDEX "equipment_categories_del_idx" ON "equipment_categories" ("deleted_at");
CREATE INDEX "equipment_categories_deleted_at_idx" ON "equipment_categories" ("deleted_at");

CREATE TABLE "equipment_documents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "equipment_id" integer NOT NULL,
 "type" varchar(30) NOT NULL DEFAULT 'certificado',
 "name" varchar(150) NOT NULL,
 "file_path" varchar(255) NOT NULL,
 "expires_at" date DEFAULT NULL,
 "uploaded_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "equipment_documents_equipment_id_foreign" ON "equipment_documents" ("equipment_id");
CREATE INDEX "equipment_documents_uploaded_by_foreign" ON "equipment_documents" ("uploaded_by");
CREATE INDEX "equipment_documents_tenant_id_index" ON "equipment_documents" ("tenant_id");
CREATE INDEX "equipment_documents_tenant_id_idx" ON "equipment_documents" ("tenant_id");

CREATE TABLE "equipment_maintenances" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "equipment_id" integer NOT NULL,
 "type" varchar(30) NOT NULL DEFAULT 'corretiva',
 "description" text NOT NULL,
 "parts_replaced" text,
 "cost" numeric DEFAULT NULL,
 "downtime_hours" numeric DEFAULT NULL,
 "performed_by" integer DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "next_maintenance_at" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "equipment_maintenances_equipment_id_foreign" ON "equipment_maintenances" ("equipment_id");
CREATE INDEX "equipment_maintenances_performed_by_foreign" ON "equipment_maintenances" ("performed_by");
CREATE INDEX "equipment_maintenances_work_order_id_foreign" ON "equipment_maintenances" ("work_order_id");
CREATE INDEX "equipment_maintenances_tenant_id_index" ON "equipment_maintenances" ("tenant_id");
CREATE INDEX "equipment_maintenances_tenant_id_idx" ON "equipment_maintenances" ("tenant_id");

CREATE TABLE "equipment_model_product" (
 "equipment_model_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 PRIMARY KEY ("equipment_model_id","product_id")
);
CREATE INDEX "equipment_model_product_product_id_foreign" ON "equipment_model_product" ("product_id");
CREATE INDEX "equipment_model_product_equipment_model_prod_tenant_idx" ON "equipment_model_product" ("tenant_id");
CREATE INDEX "equipment_model_product_tenant_id_idx" ON "equipment_model_product" ("tenant_id");

CREATE TABLE "equipment_models" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(150) NOT NULL,
 "brand" varchar(100) DEFAULT NULL,
 "category" varchar(40) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "equipment_models_tenant_id_index" ON "equipment_models" ("tenant_id");
CREATE INDEX "equipment_models_tenant_id_idx" ON "equipment_models" ("tenant_id");

CREATE TABLE "equipment_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "equipment_types_tid_slug_uq" ON "equipment_types" ("tenant_id","slug");
CREATE INDEX "equipment_types_tid_idx" ON "equipment_types" ("tenant_id");
CREATE INDEX "equipment_types_del_idx" ON "equipment_types" ("deleted_at");
CREATE INDEX "equipment_types_deleted_at_idx" ON "equipment_types" ("deleted_at");

CREATE TABLE "equipments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "type" varchar(100) NOT NULL,
 "brand" varchar(100) DEFAULT NULL,
 "model" varchar(100) DEFAULT NULL,
 "serial_number" varchar(255) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "code" varchar(30) DEFAULT NULL,
 "name" varchar(255) DEFAULT NULL,
 "category" varchar(40) NOT NULL DEFAULT 'outro',
 "manufacturer" varchar(100) DEFAULT NULL,
 "capacity" numeric DEFAULT NULL,
 "capacity_unit" varchar(10) DEFAULT NULL,
 "resolution" numeric DEFAULT NULL,
 "precision_class" varchar(10) DEFAULT NULL,
 "manufacturing_date" date DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'active',
 "location" varchar(150) DEFAULT NULL,
 "responsible_user_id" integer DEFAULT NULL,
 "purchase_date" date DEFAULT NULL,
 "purchase_value" numeric DEFAULT NULL,
 "warranty_expires_at" date DEFAULT NULL,
 "last_calibration_at" date DEFAULT NULL,
 "next_calibration_at" date DEFAULT NULL,
 "calibration_interval_months" integer DEFAULT NULL,
 "inmetro_number" varchar(50) DEFAULT NULL,
 "certificate_number" varchar(50) DEFAULT NULL,
 "tag" varchar(50) DEFAULT NULL,
 "qr_code" varchar(100) DEFAULT NULL,
 "photo_url" varchar(255) DEFAULT NULL,
 "is_critical" tinyint NOT NULL DEFAULT '0',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "deleted_at" datetime NULL DEFAULT NULL,
 "qr_token" varchar(64) DEFAULT NULL,
 "equipment_model_id" integer DEFAULT NULL,
 "accuracy_class" varchar(10) DEFAULT NULL,
 "min_capacity" numeric DEFAULT NULL,
 "max_capacity" numeric DEFAULT NULL
);
CREATE UNIQUE INDEX "equipments_qr_token_unique" ON "equipments" ("qr_token");
CREATE INDEX "equipments_customer_id_foreign" ON "equipments" ("customer_id");
CREATE INDEX "equipments_responsible_user_id_foreign" ON "equipments" ("responsible_user_id");
CREATE INDEX "equipments_tenant_id_code_index" ON "equipments" ("tenant_id","code");
CREATE INDEX "equipments_tenant_id_status_index" ON "equipments" ("tenant_id","status");
CREATE INDEX "equipments_tenant_id_next_calibration_at_index" ON "equipments" ("tenant_id","next_calibration_at");
CREATE INDEX "equipments_tenant_id_customer_id_index" ON "equipments" ("tenant_id","customer_id");
CREATE INDEX "equipments_serial_number_index" ON "equipments" ("serial_number");
CREATE INDEX "equipments_eq_tenant_active" ON "equipments" ("tenant_id","is_active");
CREATE INDEX "equipments_eq_tenant_calib" ON "equipments" ("tenant_id","next_calibration_at");
CREATE INDEX "equipments_eq_tenant_status" ON "equipments" ("tenant_id","status");
CREATE INDEX "equipments_equip_deleted_at" ON "equipments" ("tenant_id","deleted_at");
CREATE INDEX "equipments_del_idx" ON "equipments" ("deleted_at");
CREATE INDEX "equipments_equipment_model_id_fk_idx" ON "equipments" ("equipment_model_id");
CREATE INDEX "equipments_equip_tid_cid_idx" ON "equipments" ("tenant_id","customer_id");
CREATE INDEX "equipments_tenant_id_idx" ON "equipments" ("tenant_id");
CREATE INDEX "equipments_deleted_at_idx" ON "equipments" ("deleted_at");

CREATE TABLE "erp_sync_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "provider" varchar(30) NOT NULL,
 "modules" text NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'queued',
 "error_log" text,
 "records_synced" int NOT NULL DEFAULT '0',
 "synced_at" datetime NOT NULL,
 "created_by" integer DEFAULT NULL
);
CREATE INDEX "erp_sync_logs_tenant_id_index" ON "erp_sync_logs" ("tenant_id");
CREATE INDEX "erp_sync_logs_tenant_id_idx" ON "erp_sync_logs" ("tenant_id");

CREATE TABLE "escalation_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "sla_policy_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "trigger_minutes" int NOT NULL,
 "action_type" varchar(255) NOT NULL,
 "action_payload" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "escalation_rules_sla_policy_id_foreign" ON "escalation_rules" ("sla_policy_id");
CREATE INDEX "escalation_rules_tenant_id_idx" ON "escalation_rules" ("tenant_id");

CREATE TABLE "esocial_certificates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "certificate_path" varchar(255) NOT NULL,
 "certificate_password_encrypted" text NOT NULL,
 "serial_number" varchar(100) DEFAULT NULL,
 "issuer" varchar(255) DEFAULT NULL,
 "valid_from" datetime DEFAULT NULL,
 "valid_until" datetime DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "esocial_certificates_tenant_id_is_active_index" ON "esocial_certificates" ("tenant_id","is_active");
CREATE INDEX "esocial_certificates_tenant_id_idx" ON "esocial_certificates" ("tenant_id");

CREATE TABLE "esocial_events" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "event_type" varchar(10) NOT NULL,
 "related_type" varchar(100) DEFAULT NULL,
 "related_id" integer DEFAULT NULL,
 "xml_content" text,
 "protocol_number" varchar(50) DEFAULT NULL,
 "receipt_number" varchar(50) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "response_xml" text,
 "sent_at" datetime NULL DEFAULT NULL,
 "response_at" datetime NULL DEFAULT NULL,
 "error_message" text,
 "batch_id" varchar(50) DEFAULT NULL,
 "environment" varchar(10) NOT NULL DEFAULT 'production',
 "version" varchar(10) NOT NULL DEFAULT 'S-1.2',
 "retry_count" integer NOT NULL DEFAULT '0',
 "max_retries" integer NOT NULL DEFAULT '3',
 "last_retry_at" datetime NULL DEFAULT NULL,
 "next_retry_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "esocial_events_tenant_id_event_type_status_index" ON "esocial_events" ("tenant_id","event_type","status");
CREATE INDEX "esocial_events_tenant_id_related_type_related_id_index" ON "esocial_events" ("tenant_id","related_type","related_id");
CREATE INDEX "esocial_events_batch_id_index" ON "esocial_events" ("batch_id");
CREATE INDEX "esocial_events_tenant_id_idx" ON "esocial_events" ("tenant_id");

CREATE TABLE "esocial_rubrics" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "code" varchar(30) NOT NULL,
 "description" varchar(255) NOT NULL,
 "nature" varchar(20) NOT NULL,
 "type" varchar(30) NOT NULL,
 "incidence_inss" tinyint NOT NULL DEFAULT '0',
 "incidence_irrf" tinyint NOT NULL DEFAULT '0',
 "incidence_fgts" tinyint NOT NULL DEFAULT '0',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "esocial_rubrics_tenant_id_code_unique" ON "esocial_rubrics" ("tenant_id","code");

CREATE TABLE "espelho_confirmations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "year" int NOT NULL,
 "month" int NOT NULL,
 "confirmation_hash" varchar(64) NOT NULL,
 "confirmed_at" datetime NOT NULL,
 "confirmation_method" varchar NOT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "device_info" text DEFAULT NULL,
 "espelho_snapshot" text NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "espelho_confirmations_user_id_foreign" ON "espelho_confirmations" ("user_id");
CREATE INDEX "espelho_confirmations_tenant_id_idx" ON "espelho_confirmations" ("tenant_id");

CREATE TABLE "excentricity_tests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_calibration_id" integer NOT NULL,
 "position" varchar(50) NOT NULL,
 "load_applied" numeric NOT NULL,
 "indication" numeric NOT NULL,
 "error" numeric DEFAULT NULL,
 "max_permissible_error" numeric DEFAULT NULL,
 "conforms" tinyint DEFAULT NULL,
 "position_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "excentricity_tests_equipment_calibration_id_index" ON "excentricity_tests" ("equipment_calibration_id");
CREATE INDEX "excentricity_tests_tid_idx" ON "excentricity_tests" ("tenant_id");
CREATE INDEX "excentricity_tests_tenant_id_idx" ON "excentricity_tests" ("tenant_id");

CREATE TABLE "expense_categories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "color" varchar(7) NOT NULL DEFAULT '#6b7280',
 "active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "budget_limit" numeric DEFAULT NULL,
 "default_affects_net_value" tinyint NOT NULL DEFAULT '0',
 "default_affects_technician_cash" tinyint NOT NULL DEFAULT '1'
);
CREATE INDEX "expense_categories_ec_deleted_at" ON "expense_categories" ("deleted_at");
CREATE INDEX "expense_categories_tid_idx" ON "expense_categories" ("tenant_id");
CREATE INDEX "expense_categories_del_idx" ON "expense_categories" ("deleted_at");
CREATE INDEX "expense_categories_expcat_tid_act_idx" ON "expense_categories" ("tenant_id","active");
CREATE INDEX "expense_categories_tenant_id_idx" ON "expense_categories" ("tenant_id");
CREATE INDEX "expense_categories_deleted_at_idx" ON "expense_categories" ("deleted_at");

CREATE TABLE "expense_status_history" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "expense_id" integer NOT NULL,
 "changed_by" integer NOT NULL,
 "from_status" varchar(20) DEFAULT NULL,
 "to_status" varchar(20) NOT NULL,
 "reason" varchar(500) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "expense_status_history_changed_by_foreign" ON "expense_status_history" ("changed_by");
CREATE INDEX "expense_status_history_expense_id_created_at_index" ON "expense_status_history" ("expense_id","created_at");
CREATE INDEX "expense_status_history_tenant_id_idx" ON "expense_status_history" ("tenant_id");

CREATE TABLE "expenses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "expense_category_id" integer DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "description" varchar(255) NOT NULL,
 "amount" numeric NOT NULL,
 "expense_date" date NOT NULL,
 "payment_method" varchar(30) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "notes" text,
 "receipt_path" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "affects_technician_cash" tinyint NOT NULL DEFAULT '0',
 "chart_of_account_id" integer DEFAULT NULL,
 "rejection_reason" varchar(500) DEFAULT NULL,
 "affects_net_value" tinyint NOT NULL DEFAULT '1',
 "km_quantity" numeric DEFAULT NULL,
 "km_rate" numeric DEFAULT NULL,
 "km_billed_to_client" tinyint NOT NULL DEFAULT '0',
 "reviewed_by" integer DEFAULT NULL,
 "reviewed_at" datetime NULL DEFAULT NULL,
 "cost_center_id" integer DEFAULT NULL,
 "reimbursement_ap_id" integer DEFAULT NULL,
 "payroll_id" integer DEFAULT NULL,
 "payroll_line_id" integer DEFAULT NULL,
 "reference_type" varchar(50) DEFAULT NULL,
 "reference_id" integer DEFAULT NULL
);
CREATE INDEX "expenses_tenant_id_status_expense_date_index" ON "expenses" ("tenant_id","status","expense_date");
CREATE INDEX "expenses_chart_of_account_id_foreign" ON "expenses" ("chart_of_account_id");
CREATE INDEX "expenses_cost_center_id_foreign" ON "expenses" ("cost_center_id");
CREATE INDEX "expenses_exp_work_order" ON "expenses" ("work_order_id");
CREATE INDEX "expenses_exp_created_by" ON "expenses" ("created_by");
CREATE INDEX "expenses_exp_category" ON "expenses" ("expense_category_id");
CREATE INDEX "expenses_approved_by_foreign" ON "expenses" ("approved_by");
CREATE INDEX "expenses_exp_deleted_at" ON "expenses" ("tenant_id","deleted_at");
CREATE INDEX "expenses_exp_reviewed_by" ON "expenses" ("reviewed_by");
CREATE INDEX "expenses_del_idx" ON "expenses" ("deleted_at");
CREATE INDEX "expenses_payroll_id_foreign" ON "expenses" ("payroll_id");
CREATE INDEX "expenses_payroll_line_id_foreign" ON "expenses" ("payroll_line_id");
CREATE INDEX "expenses_tenant_id_idx" ON "expenses" ("tenant_id");
CREATE INDEX "expenses_deleted_at_idx" ON "expenses" ("deleted_at");

CREATE TABLE "export_jobs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "type" varchar(50) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "file_path" varchar(255) DEFAULT NULL,
 "filters" text DEFAULT NULL,
 "started_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "error" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "export_jobs_tenant_id_index" ON "export_jobs" ("tenant_id");
CREATE INDEX "export_jobs_tenant_id_idx" ON "export_jobs" ("tenant_id");

CREATE TABLE "failed_jobs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "uuid" varchar(255) NOT NULL,
 "connection" text NOT NULL,
 "queue" text NOT NULL,
 "payload" text NOT NULL,
 "exception" text NOT NULL,
 "failed_at" datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" ON "failed_jobs" ("uuid");

CREATE TABLE "financial_checks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "number" varchar(255) NOT NULL,
 "bank" varchar(255) NOT NULL,
 "amount" numeric NOT NULL,
 "due_date" date NOT NULL,
 "issuer" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "financial_checks_tenant_id_status_due_date_index" ON "financial_checks" ("tenant_id","status","due_date");
CREATE INDEX "financial_checks_fc_tenant_status_due" ON "financial_checks" ("tenant_id","status","due_date");
CREATE INDEX "financial_checks_del_idx" ON "financial_checks" ("deleted_at");
CREATE INDEX "financial_checks_tenant_id_idx" ON "financial_checks" ("tenant_id");
CREATE INDEX "financial_checks_deleted_at_idx" ON "financial_checks" ("deleted_at");

CREATE TABLE "fiscal_audit_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fiscal_note_id" integer DEFAULT NULL,
 "action" varchar(255) NOT NULL,
 "user_id" integer DEFAULT NULL,
 "user_name" varchar(255) DEFAULT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fiscal_audit_logs_fiscal_note_id_foreign" ON "fiscal_audit_logs" ("fiscal_note_id");
CREATE INDEX "fiscal_audit_logs_user_id_foreign" ON "fiscal_audit_logs" ("user_id");
CREATE INDEX "fiscal_audit_logs_tenant_id_fiscal_note_id_index" ON "fiscal_audit_logs" ("tenant_id","fiscal_note_id");
CREATE INDEX "fiscal_audit_logs_created_at_index" ON "fiscal_audit_logs" ("created_at");
CREATE INDEX "fiscal_audit_logs_tenant_id_idx" ON "fiscal_audit_logs" ("tenant_id");

CREATE TABLE "fiscal_events" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "fiscal_note_id" integer DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "event_type" varchar(30) NOT NULL,
 "protocol_number" varchar(50) DEFAULT NULL,
 "description" text,
 "request_payload" text,
 "response_payload" text,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "error_message" text,
 "user_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fiscal_events_user_id_foreign" ON "fiscal_events" ("user_id");
CREATE INDEX "fiscal_events_fiscal_note_id_event_type_index" ON "fiscal_events" ("fiscal_note_id","event_type");
CREATE INDEX "fiscal_events_tenant_id_created_at_index" ON "fiscal_events" ("tenant_id","created_at");
CREATE INDEX "fiscal_events_tenant_id_idx" ON "fiscal_events" ("tenant_id");

CREATE TABLE "fiscal_invoice_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fiscal_invoice_id" integer NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "quantity" numeric NOT NULL DEFAULT '1.00',
 "unit_price" numeric NOT NULL DEFAULT '0.00',
 "total" numeric NOT NULL DEFAULT '0.00',
 "product_id" integer DEFAULT NULL,
 "service_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fiscal_invoice_items_fii_tenant_invoice_idx" ON "fiscal_invoice_items" ("tenant_id","fiscal_invoice_id");
CREATE INDEX "fiscal_invoice_items_tenant_id_idx" ON "fiscal_invoice_items" ("tenant_id");

CREATE TABLE "fiscal_invoices" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "number" varchar(255) DEFAULT NULL,
 "series" varchar(10) DEFAULT NULL,
 "type" varchar(20) DEFAULT NULL,
 "customer_id" integer DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "total" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "issued_at" datetime NULL DEFAULT NULL,
 "xml" text,
 "pdf_url" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "fiscal_invoices_tenant_number_unique" ON "fiscal_invoices" ("tenant_id","number");
CREATE INDEX "fiscal_invoices_finv_tenant_number_idx" ON "fiscal_invoices" ("tenant_id","number");
CREATE INDEX "fiscal_invoices_deleted_at_idx" ON "fiscal_invoices" ("deleted_at");

CREATE TABLE "fiscal_notes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(10) NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "quote_id" integer DEFAULT NULL,
 "customer_id" integer NOT NULL,
 "number" varchar(255) DEFAULT NULL,
 "series" varchar(255) DEFAULT NULL,
 "access_key" varchar(255) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "provider" varchar(30) NOT NULL DEFAULT 'nuvemfiscal',
 "provider_id" varchar(255) DEFAULT NULL,
 "total_amount" numeric NOT NULL DEFAULT '0.00',
 "issued_at" datetime NULL DEFAULT NULL,
 "cancelled_at" datetime NULL DEFAULT NULL,
 "cancel_reason" text,
 "pdf_url" text,
 "xml_url" text,
 "error_message" text,
 "raw_response" text DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "reference" varchar(100) DEFAULT NULL,
 "nature_of_operation" varchar(255) DEFAULT NULL,
 "cfop" varchar(10) DEFAULT NULL,
 "items_data" text DEFAULT NULL,
 "protocol_number" varchar(50) DEFAULT NULL,
 "environment" varchar(20) NOT NULL DEFAULT 'homologation',
 "contingency_mode" tinyint NOT NULL DEFAULT '0',
 "verification_code" varchar(100) DEFAULT NULL,
 "pdf_path" varchar(255) DEFAULT NULL,
 "xml_path" varchar(255) DEFAULT NULL,
 "parent_note_id" integer DEFAULT NULL,
 "payment_data" text DEFAULT NULL,
 "email_retry_count" int NOT NULL DEFAULT '0',
 "last_email_sent_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "fiscal_notes_access_key_unique" ON "fiscal_notes" ("access_key");
CREATE INDEX "fiscal_notes_quote_id_foreign" ON "fiscal_notes" ("quote_id");
CREATE INDEX "fiscal_notes_created_by_foreign" ON "fiscal_notes" ("created_by");
CREATE INDEX "fiscal_notes_tenant_id_type_index" ON "fiscal_notes" ("tenant_id","type");
CREATE INDEX "fiscal_notes_tenant_id_status_index" ON "fiscal_notes" ("tenant_id","status");
CREATE INDEX "fiscal_notes_tenant_id_customer_id_index" ON "fiscal_notes" ("tenant_id","customer_id");
CREATE INDEX "fiscal_notes_work_order_id_index" ON "fiscal_notes" ("work_order_id");
CREATE INDEX "fiscal_notes_reference_index" ON "fiscal_notes" ("reference");
CREATE INDEX "fiscal_notes_parent_note_id_foreign" ON "fiscal_notes" ("parent_note_id");
CREATE INDEX "fiscal_notes_fn_tenant_status" ON "fiscal_notes" ("tenant_id","status");
CREATE INDEX "fiscal_notes_fn_customer" ON "fiscal_notes" ("customer_id");
CREATE INDEX "fiscal_notes_provider_id_index" ON "fiscal_notes" ("provider_id");
CREATE INDEX "fiscal_notes_tenant_id_idx" ON "fiscal_notes" ("tenant_id");
CREATE INDEX "fiscal_notes_deleted_at_idx" ON "fiscal_notes" ("deleted_at");

CREATE TABLE "fiscal_scheduled_emissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "quote_id" integer DEFAULT NULL,
 "customer_id" integer NOT NULL,
 "payload" text NOT NULL,
 "scheduled_at" datetime NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "fiscal_note_id" integer DEFAULT NULL,
 "error_message" text,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fiscal_scheduled_emissions_work_order_id_foreign" ON "fiscal_scheduled_emissions" ("work_order_id");
CREATE INDEX "fiscal_scheduled_emissions_quote_id_foreign" ON "fiscal_scheduled_emissions" ("quote_id");
CREATE INDEX "fiscal_scheduled_emissions_customer_id_foreign" ON "fiscal_scheduled_emissions" ("customer_id");
CREATE INDEX "fiscal_scheduled_emissions_fiscal_note_id_foreign" ON "fiscal_scheduled_emissions" ("fiscal_note_id");
CREATE INDEX "fiscal_scheduled_emissions_created_by_foreign" ON "fiscal_scheduled_emissions" ("created_by");
CREATE INDEX "fiscal_scheduled_emissions_tenant_id_status_index" ON "fiscal_scheduled_emissions" ("tenant_id","status");
CREATE INDEX "fiscal_scheduled_emissions_scheduled_at_index" ON "fiscal_scheduled_emissions" ("scheduled_at");
CREATE INDEX "fiscal_scheduled_emissions_fse_tenant_customer_status" ON "fiscal_scheduled_emissions" ("tenant_id","customer_id","status");
CREATE INDEX "fiscal_scheduled_emissions_tenant_id_idx" ON "fiscal_scheduled_emissions" ("tenant_id");

CREATE TABLE "fiscal_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "type" varchar(255) NOT NULL,
 "template_data" text NOT NULL,
 "usage_count" int NOT NULL DEFAULT '0',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fiscal_templates_created_by_foreign" ON "fiscal_templates" ("created_by");
CREATE INDEX "fiscal_templates_tenant_id_index" ON "fiscal_templates" ("tenant_id");
CREATE INDEX "fiscal_templates_tenant_id_idx" ON "fiscal_templates" ("tenant_id");

CREATE TABLE "fiscal_webhooks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "url" varchar(255) NOT NULL,
 "events" text DEFAULT NULL,
 "secret" varchar(64) DEFAULT NULL,
 "active" tinyint NOT NULL DEFAULT '1',
 "failure_count" int NOT NULL DEFAULT '0',
 "last_triggered_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fiscal_webhooks_tenant_id_index" ON "fiscal_webhooks" ("tenant_id");
CREATE INDEX "fiscal_webhooks_tenant_id_idx" ON "fiscal_webhooks" ("tenant_id");

CREATE TABLE "fleet_fuel_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_id" integer NOT NULL,
 "date" date DEFAULT NULL,
 "fuel_type" varchar(30) DEFAULT NULL,
 "liters" numeric DEFAULT NULL,
 "cost" numeric DEFAULT NULL,
 "odometer" int DEFAULT NULL,
 "station" varchar(255) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fleet_fuel_entries_fleet_fuel_tenant_idx" ON "fleet_fuel_entries" ("tenant_id","fleet_id");
CREATE INDEX "fleet_fuel_entries_tenant_id_idx" ON "fleet_fuel_entries" ("tenant_id");

CREATE TABLE "fleet_fuel_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "fleet_fuel_types_tid_slug_uq" ON "fleet_fuel_types" ("tenant_id","slug");
CREATE INDEX "fleet_fuel_types_tid_idx" ON "fleet_fuel_types" ("tenant_id");
CREATE INDEX "fleet_fuel_types_del_idx" ON "fleet_fuel_types" ("deleted_at");
CREATE INDEX "fleet_fuel_types_deleted_at_idx" ON "fleet_fuel_types" ("deleted_at");

CREATE TABLE "fleet_maintenances" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_id" integer NOT NULL,
 "type" varchar(50) DEFAULT NULL,
 "description" text,
 "date" date DEFAULT NULL,
 "cost" numeric DEFAULT NULL,
 "odometer" int DEFAULT NULL,
 "next_date" date DEFAULT NULL,
 "status" varchar(30) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fleet_maintenances_fleet_maint_tenant_idx" ON "fleet_maintenances" ("tenant_id","fleet_id");
CREATE INDEX "fleet_maintenances_tenant_id_idx" ON "fleet_maintenances" ("tenant_id");

CREATE TABLE "fleet_telemetry" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "vehicle_id" integer NOT NULL,
 "odometer" int DEFAULT NULL,
 "dtc_fault_codes" varchar(255) DEFAULT NULL,
 "engine_temperature" numeric DEFAULT NULL,
 "fuel_level_pct" numeric DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fleet_telemetry_vehicle_id_foreign" ON "fleet_telemetry" ("vehicle_id");
CREATE INDEX "fleet_telemetry_tid_idx" ON "fleet_telemetry" ("tenant_id");
CREATE INDEX "fleet_telemetry_tenant_id_idx" ON "fleet_telemetry" ("tenant_id");

CREATE TABLE "fleet_trips" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_id" integer NOT NULL,
 "driver_user_id" integer DEFAULT NULL,
 "date" date DEFAULT NULL,
 "origin" varchar(255) DEFAULT NULL,
 "destination" varchar(255) DEFAULT NULL,
 "distance_km" numeric DEFAULT NULL,
 "purpose" varchar(255) DEFAULT NULL,
 "odometer_start" int DEFAULT NULL,
 "odometer_end" int DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fleet_trips_fleet_trip_tenant_idx" ON "fleet_trips" ("tenant_id","fleet_id");
CREATE INDEX "fleet_trips_tenant_id_idx" ON "fleet_trips" ("tenant_id");

CREATE TABLE "fleet_vehicle_statuses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "fleet_vehicle_statuses_tid_slug_uq" ON "fleet_vehicle_statuses" ("tenant_id","slug");
CREATE INDEX "fleet_vehicle_statuses_tid_idx" ON "fleet_vehicle_statuses" ("tenant_id");
CREATE INDEX "fleet_vehicle_statuses_del_idx" ON "fleet_vehicle_statuses" ("deleted_at");
CREATE INDEX "fleet_vehicle_statuses_deleted_at_idx" ON "fleet_vehicle_statuses" ("deleted_at");

CREATE TABLE "fleet_vehicle_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "fleet_vehicle_types_tid_slug_uq" ON "fleet_vehicle_types" ("tenant_id","slug");
CREATE INDEX "fleet_vehicle_types_tid_idx" ON "fleet_vehicle_types" ("tenant_id");
CREATE INDEX "fleet_vehicle_types_del_idx" ON "fleet_vehicle_types" ("deleted_at");
CREATE INDEX "fleet_vehicle_types_deleted_at_idx" ON "fleet_vehicle_types" ("deleted_at");

CREATE TABLE "fleet_vehicles" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "plate" varchar(10) NOT NULL,
 "brand" varchar(100) DEFAULT NULL,
 "model" varchar(100) DEFAULT NULL,
 "year" int DEFAULT NULL,
 "color" varchar(50) DEFAULT NULL,
 "type" varchar(50) NOT NULL DEFAULT 'car',
 "fuel_type" varchar(30) NOT NULL DEFAULT 'flex',
 "odometer_km" int NOT NULL DEFAULT '0',
 "renavam" varchar(20) DEFAULT NULL,
 "chassis" varchar(30) DEFAULT NULL,
 "crlv_expiry" date DEFAULT NULL,
 "insurance_expiry" date DEFAULT NULL,
 "next_maintenance" date DEFAULT NULL,
 "tire_change_date" date DEFAULT NULL,
 "purchase_value" numeric DEFAULT NULL,
 "avg_fuel_consumption" numeric DEFAULT NULL,
 "cost_per_km" numeric DEFAULT NULL,
 "cnh_expiry_driver" date DEFAULT NULL,
 "assigned_user_id" integer DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'active',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fleet_vehicles_assigned_user_id_foreign" ON "fleet_vehicles" ("assigned_user_id");
CREATE INDEX "fleet_vehicles_plate_index" ON "fleet_vehicles" ("plate");
CREATE INDEX "fleet_vehicles_tid_idx" ON "fleet_vehicles" ("tenant_id");
CREATE INDEX "fleet_vehicles_del_idx" ON "fleet_vehicles" ("deleted_at");
CREATE INDEX "fleet_vehicles_tid_st_idx" ON "fleet_vehicles" ("tenant_id","status");
CREATE INDEX "fleet_vehicles_tenant_id_idx" ON "fleet_vehicles" ("tenant_id");
CREATE INDEX "fleet_vehicles_deleted_at_idx" ON "fleet_vehicles" ("deleted_at");

CREATE TABLE "fleets" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "plate" varchar(20) DEFAULT NULL,
 "brand" varchar(100) DEFAULT NULL,
 "model" varchar(100) DEFAULT NULL,
 "year" varchar(10) DEFAULT NULL,
 "color" varchar(50) DEFAULT NULL,
 "type" varchar(30) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'active',
 "mileage" numeric DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "fleets_tenant_id_index" ON "fleets" ("tenant_id");
CREATE INDEX "fleets_tenant_id_idx" ON "fleets" ("tenant_id");
CREATE INDEX "fleets_deleted_at_idx" ON "fleets" ("deleted_at");

CREATE TABLE "follow_up_channels" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "follow_up_channels_tid_slug_uq" ON "follow_up_channels" ("tenant_id","slug");
CREATE INDEX "follow_up_channels_tid_idx" ON "follow_up_channels" ("tenant_id");
CREATE INDEX "follow_up_channels_del_idx" ON "follow_up_channels" ("deleted_at");
CREATE INDEX "follow_up_channels_deleted_at_idx" ON "follow_up_channels" ("deleted_at");

CREATE TABLE "follow_up_statuses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "follow_up_statuses_tid_slug_uq" ON "follow_up_statuses" ("tenant_id","slug");
CREATE INDEX "follow_up_statuses_tid_idx" ON "follow_up_statuses" ("tenant_id");
CREATE INDEX "follow_up_statuses_del_idx" ON "follow_up_statuses" ("deleted_at");
CREATE INDEX "follow_up_statuses_deleted_at_idx" ON "follow_up_statuses" ("deleted_at");

CREATE TABLE "follow_ups" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "followable_type" varchar(255) NOT NULL,
 "followable_id" integer NOT NULL,
 "assigned_to" integer NOT NULL,
 "scheduled_at" datetime NOT NULL,
 "completed_at" datetime DEFAULT NULL,
 "channel" varchar(30) NOT NULL DEFAULT 'phone',
 "notes" text,
 "result" varchar(50) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "follow_ups_followable_type_followable_id_index" ON "follow_ups" ("followable_type","followable_id");
CREATE INDEX "follow_ups_assigned_to_foreign" ON "follow_ups" ("assigned_to");
CREATE INDEX "follow_ups_tid_idx" ON "follow_ups" ("tenant_id");
CREATE INDEX "follow_ups_tid_st_idx" ON "follow_ups" ("tenant_id","status");
CREATE INDEX "follow_ups_tenant_id_idx" ON "follow_ups" ("tenant_id");

CREATE TABLE "fuel_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_vehicle_id" integer DEFAULT NULL,
 "driver_id" integer DEFAULT NULL,
 "date" date DEFAULT NULL,
 "odometer_km" int DEFAULT NULL,
 "liters" numeric DEFAULT NULL,
 "price_per_liter" numeric DEFAULT NULL,
 "total_value" numeric DEFAULT NULL,
 "fuel_type" varchar(50) DEFAULT NULL,
 "gas_station" varchar(255) DEFAULT NULL,
 "consumption_km_l" numeric DEFAULT NULL,
 "receipt_path" varchar(255) DEFAULT NULL,
 "distance_km" numeric DEFAULT NULL,
 "total_cost" numeric DEFAULT NULL,
 "created_by" integer DEFAULT NULL
);
CREATE INDEX "fuel_logs_tenant_date_idx" ON "fuel_logs" ("tenant_id","date");
CREATE INDEX "fuel_logs_vehicle_date_idx" ON "fuel_logs" ("fleet_vehicle_id","date");
CREATE INDEX "fuel_logs_driver_id_index" ON "fuel_logs" ("driver_id");
CREATE INDEX "fuel_logs_tenant_id_idx" ON "fuel_logs" ("tenant_id");

CREATE TABLE "fueling_fuel_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "fueling_fuel_types_tid_slug_uq" ON "fueling_fuel_types" ("tenant_id","slug");
CREATE INDEX "fueling_fuel_types_tid_idx" ON "fueling_fuel_types" ("tenant_id");
CREATE INDEX "fueling_fuel_types_del_idx" ON "fueling_fuel_types" ("deleted_at");
CREATE INDEX "fueling_fuel_types_deleted_at_idx" ON "fueling_fuel_types" ("deleted_at");

CREATE TABLE "fueling_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "fueling_date" date NOT NULL,
 "vehicle_plate" varchar(20) NOT NULL,
 "odometer_km" numeric NOT NULL,
 "gas_station_name" varchar(150) DEFAULT NULL,
 "gas_station_lat" numeric DEFAULT NULL,
 "gas_station_lng" numeric DEFAULT NULL,
 "fuel_type" varchar(30) NOT NULL DEFAULT 'diesel',
 "liters" numeric NOT NULL,
 "price_per_liter" numeric NOT NULL,
 "total_amount" numeric NOT NULL,
 "receipt_path" varchar(255) DEFAULT NULL,
 "notes" text,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "rejection_reason" varchar(500) DEFAULT NULL,
 "affects_technician_cash" tinyint NOT NULL DEFAULT '0'
);
CREATE INDEX "fueling_logs_user_id_foreign" ON "fueling_logs" ("user_id");
CREATE INDEX "fueling_logs_work_order_id_foreign" ON "fueling_logs" ("work_order_id");
CREATE INDEX "fueling_logs_approved_by_foreign" ON "fueling_logs" ("approved_by");
CREATE INDEX "fueling_logs_tenant_id_user_id_fueling_date_index" ON "fueling_logs" ("tenant_id","user_id","fueling_date");
CREATE INDEX "fueling_logs_del_idx" ON "fueling_logs" ("deleted_at");
CREATE INDEX "fueling_logs_tenant_id_idx" ON "fueling_logs" ("tenant_id");
CREATE INDEX "fueling_logs_deleted_at_idx" ON "fueling_logs" ("deleted_at");

CREATE TABLE "fund_transfers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "bank_account_id" integer NOT NULL,
 "to_user_id" integer NOT NULL,
 "amount" numeric NOT NULL,
 "transfer_date" date NOT NULL,
 "payment_method" varchar(30) NOT NULL,
 "description" varchar(255) NOT NULL,
 "account_payable_id" integer DEFAULT NULL,
 "technician_cash_transaction_id" integer DEFAULT NULL,
 "status" varchar NOT NULL DEFAULT 'completed',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "from_account_id" integer DEFAULT NULL,
 "to_account_id" integer DEFAULT NULL
);
CREATE INDEX "fund_transfers_to_user_id_foreign" ON "fund_transfers" ("to_user_id");
CREATE INDEX "fund_transfers_account_payable_id_foreign" ON "fund_transfers" ("account_payable_id");
CREATE INDEX "fund_transfers_technician_cash_transaction_id_foreign" ON "fund_transfers" ("technician_cash_transaction_id");
CREATE INDEX "fund_transfers_tenant_id_status_transfer_date_index" ON "fund_transfers" ("tenant_id","status","transfer_date");
CREATE INDEX "fund_transfers_tenant_id_to_user_id_index" ON "fund_transfers" ("tenant_id","to_user_id");
CREATE INDEX "fund_transfers_created_by_foreign" ON "fund_transfers" ("created_by");
CREATE INDEX "fund_transfers_ft_deleted_at" ON "fund_transfers" ("tenant_id","deleted_at");
CREATE INDEX "fund_transfers_del_idx" ON "fund_transfers" ("deleted_at");
CREATE INDEX "fund_transfers_bank_account_id_fk_idx" ON "fund_transfers" ("bank_account_id");
CREATE INDEX "fund_transfers_tenant_id_idx" ON "fund_transfers" ("tenant_id");
CREATE INDEX "fund_transfers_deleted_at_idx" ON "fund_transfers" ("deleted_at");

CREATE TABLE "funnel_email_automations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "pipeline_stage_id" integer NOT NULL,
 "trigger" varchar(255) NOT NULL,
 "trigger_days" int DEFAULT NULL,
 "subject" varchar(255) NOT NULL,
 "body" text NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "funnel_email_automations_tenant_id_pipeline_stage_id_index" ON "funnel_email_automations" ("tenant_id","pipeline_stage_id");
CREATE INDEX "funnel_email_automations_pipeline_stage_id_foreign" ON "funnel_email_automations" ("pipeline_stage_id");
CREATE INDEX "funnel_email_automations_tenant_id_idx" ON "funnel_email_automations" ("tenant_id");

CREATE TABLE "gamification_badges" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" text,
 "icon" varchar(255) DEFAULT NULL,
 "color" varchar(255) DEFAULT NULL,
 "category" varchar(255) NOT NULL,
 "metric" varchar(255) NOT NULL,
 "threshold" int NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "gamification_badges_slug_unique" ON "gamification_badges" ("slug");
CREATE INDEX "gamification_badges_gb_tenant_cat_idx" ON "gamification_badges" ("tenant_id","category");
CREATE INDEX "gamification_badges_tenant_id_idx" ON "gamification_badges" ("tenant_id");

CREATE TABLE "gamification_scores" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "period" varchar(255) NOT NULL,
 "period_type" varchar(255) NOT NULL DEFAULT 'monthly',
 "visits_count" int NOT NULL DEFAULT '0',
 "deals_won" int NOT NULL DEFAULT '0',
 "deals_value" numeric NOT NULL DEFAULT '0.00',
 "new_clients" int NOT NULL DEFAULT '0',
 "activities_count" int NOT NULL DEFAULT '0',
 "coverage_percent" numeric NOT NULL DEFAULT '0.00',
 "csat_avg" numeric NOT NULL DEFAULT '0.00',
 "commitments_on_time" int NOT NULL DEFAULT '0',
 "commitments_total" int NOT NULL DEFAULT '0',
 "total_points" int NOT NULL DEFAULT '0',
 "rank_position" int DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "gs_tenant_user_period_uniq" ON "gamification_scores" ("tenant_id","user_id","period");
CREATE INDEX "gamification_scores_user_id_foreign" ON "gamification_scores" ("user_id");

CREATE TABLE "gamification_user_badges" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "badge_id" integer NOT NULL,
 "earned_at" datetime NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "gub_user_badge_uniq" ON "gamification_user_badges" ("user_id","badge_id");
CREATE INDEX "gamification_user_badges_badge_id_foreign" ON "gamification_user_badges" ("badge_id");
CREATE INDEX "gamification_user_badges_tid_idx" ON "gamification_user_badges" ("tenant_id");
CREATE INDEX "gamification_user_badges_tenant_id_index" ON "gamification_user_badges" ("tenant_id");
CREATE INDEX "gamification_user_badges_tenant_id_idx" ON "gamification_user_badges" ("tenant_id");

CREATE TABLE "geo_login_alerts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "ip_address" varchar(45) NOT NULL,
 "city" varchar(255) DEFAULT NULL,
 "country" varchar(2) DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "is_suspicious" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NOT NULL
);
CREATE INDEX "geo_login_alerts_tenant_id_index" ON "geo_login_alerts" ("tenant_id");
CREATE INDEX "geo_login_alerts_user_id_index" ON "geo_login_alerts" ("user_id");
CREATE INDEX "geo_login_alerts_tenant_id_idx" ON "geo_login_alerts" ("tenant_id");

CREATE TABLE "geofence_locations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "latitude" numeric NOT NULL,
 "longitude" numeric NOT NULL,
 "radius_meters" int NOT NULL DEFAULT '200',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "linked_entity_type" varchar(255) DEFAULT NULL,
 "linked_entity_id" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "geofence_locations_linked_entity_type_linked_entity_id_index" ON "geofence_locations" ("linked_entity_type","linked_entity_id");
CREATE INDEX "geofence_locations_tid_idx" ON "geofence_locations" ("tenant_id");
CREATE INDEX "geofence_locations_tenant_id_idx" ON "geofence_locations" ("tenant_id");

CREATE TABLE "holidays" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "date" date NOT NULL,
 "is_national" tinyint NOT NULL DEFAULT '1',
 "is_recurring" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "holidays_tenant_id_date_unique" ON "holidays" ("tenant_id","date");

CREATE TABLE "hour_bank_policies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "regime_type" varchar(255) NOT NULL DEFAULT 'individual_mensal',
 "compensation_period_days" int NOT NULL DEFAULT '30',
 "max_positive_balance_minutes" int DEFAULT NULL,
 "max_negative_balance_minutes" int DEFAULT NULL,
 "block_on_negative_exceeded" tinyint NOT NULL DEFAULT '1',
 "auto_compensate" tinyint NOT NULL DEFAULT '0',
 "convert_expired_to_payment" tinyint NOT NULL DEFAULT '0',
 "overtime_50_multiplier" numeric NOT NULL DEFAULT '1.50',
 "overtime_100_multiplier" numeric NOT NULL DEFAULT '2.00',
 "applicable_roles" text DEFAULT NULL,
 "applicable_teams" text DEFAULT NULL,
 "applicable_unions" text DEFAULT NULL,
 "requires_two_level_approval" tinyint NOT NULL DEFAULT '1',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "hour_bank_policies_tenant_id_is_active_index" ON "hour_bank_policies" ("tenant_id","is_active");
CREATE INDEX "hour_bank_policies_deleted_at_idx" ON "hour_bank_policies" ("deleted_at");

CREATE TABLE "hour_bank_transactions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "journey_entry_id" integer DEFAULT NULL,
 "type" varchar(20) NOT NULL,
 "hours" numeric NOT NULL,
 "balance_before" numeric NOT NULL DEFAULT '0.00',
 "balance_after" numeric NOT NULL DEFAULT '0.00',
 "reference_date" date NOT NULL,
 "expired_at" datetime NULL DEFAULT NULL,
 "payout_payroll_id" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "hour_bank_transactions_user_id_foreign" ON "hour_bank_transactions" ("user_id");
CREATE INDEX "hour_bank_transactions_journey_entry_id_foreign" ON "hour_bank_transactions" ("journey_entry_id");
CREATE INDEX "hour_bank_transactions_tenant_id_user_id_index" ON "hour_bank_transactions" ("tenant_id","user_id");
CREATE INDEX "hour_bank_transactions_tenant_id_user_id_type_index" ON "hour_bank_transactions" ("tenant_id","user_id","type");
CREATE INDEX "hour_bank_transactions_tenant_id_reference_date_index" ON "hour_bank_transactions" ("tenant_id","reference_date");
CREATE INDEX "hour_bank_transactions_tenant_id_idx" ON "hour_bank_transactions" ("tenant_id");

CREATE TABLE "immutable_backups" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(20) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'queued',
 "retention_days" int NOT NULL DEFAULT '30',
 "file_path" varchar(255) DEFAULT NULL,
 "size_bytes" integer DEFAULT NULL,
 "requested_by" integer DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "immutable_backups_tenant_id_index" ON "immutable_backups" ("tenant_id");
CREATE INDEX "immutable_backups_tenant_id_idx" ON "immutable_backups" ("tenant_id");

CREATE TABLE "import_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "entity_type" varchar(30) NOT NULL,
 "name" varchar(100) NOT NULL,
 "mapping" text NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "import_templates_tenant_id_entity_type_name_unique" ON "import_templates" ("tenant_id","entity_type","name");

CREATE TABLE "important_dates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "type" varchar(255) NOT NULL,
 "date" date NOT NULL,
 "recurring_yearly" tinyint NOT NULL DEFAULT '1',
 "remind_days_before" int NOT NULL DEFAULT '7',
 "contact_name" varchar(255) DEFAULT NULL,
 "notes" text,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "important_dates_customer_id_foreign" ON "important_dates" ("customer_id");
CREATE INDEX "important_dates_impdate_tenant_date_idx" ON "important_dates" ("tenant_id","date");
CREATE INDEX "important_dates_impdate_tenant_cust_idx" ON "important_dates" ("tenant_id","customer_id");
CREATE INDEX "important_dates_tenant_id_idx" ON "important_dates" ("tenant_id");

CREATE TABLE "imports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "entity_type" varchar(30) NOT NULL,
 "file_name" varchar(255) NOT NULL,
 "total_rows" int NOT NULL DEFAULT '0',
 "inserted" int NOT NULL DEFAULT '0',
 "updated" int NOT NULL DEFAULT '0',
 "skipped" int NOT NULL DEFAULT '0',
 "errors" int NOT NULL DEFAULT '0',
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "mapping" text DEFAULT NULL,
 "error_log" text DEFAULT NULL,
 "duplicate_strategy" varchar(20) NOT NULL DEFAULT 'skip',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "separator" varchar(10) NOT NULL DEFAULT ';',
 "imported_ids" text DEFAULT NULL,
 "original_name" varchar(255) DEFAULT NULL,
 "progress" tinyint NOT NULL DEFAULT '0',
 "type" varchar(50) DEFAULT NULL,
 "rows_processed" int DEFAULT NULL,
 "rows_failed" int DEFAULT NULL
);
CREATE INDEX "imports_tenant_id_entity_type_index" ON "imports" ("tenant_id","entity_type");
CREATE INDEX "imports_status_index" ON "imports" ("status");
CREATE INDEX "imports_user_id_index" ON "imports" ("user_id");
CREATE INDEX "imports_imp_tenant_status" ON "imports" ("tenant_id","status");
CREATE INDEX "imports_user_id_fk_idx" ON "imports" ("user_id");
CREATE INDEX "imports_tenant_id_idx" ON "imports" ("tenant_id");

CREATE TABLE "inmetro_base_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "base_lat" numeric DEFAULT NULL,
 "base_lng" numeric DEFAULT NULL,
 "base_address" varchar(255) DEFAULT NULL,
 "base_city" varchar(255) DEFAULT NULL,
 "base_state" varchar(2) DEFAULT NULL,
 "max_distance_km" int NOT NULL DEFAULT '200',
 "enrichment_sources" text DEFAULT NULL,
 "last_enrichment_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "psie_username" varchar(255) DEFAULT NULL,
 "psie_password" text,
 "last_rejection_check_at" datetime NULL DEFAULT NULL,
 "notification_roles" text DEFAULT NULL,
 "whatsapp_message_template" text,
 "email_subject_template" varchar(255) DEFAULT NULL,
 "email_body_template" text
);
CREATE UNIQUE INDEX "inmetro_base_configs_tenant_id_unique" ON "inmetro_base_configs" ("tenant_id");

CREATE TABLE "inmetro_competitor_snapshots" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "competitor_id" integer DEFAULT NULL,
 "snapshot_type" varchar(20) NOT NULL DEFAULT 'monthly',
 "period_start" date NOT NULL,
 "period_end" date NOT NULL,
 "instrument_count" int NOT NULL DEFAULT '0',
 "repair_count" int NOT NULL DEFAULT '0',
 "new_instruments" int NOT NULL DEFAULT '0',
 "lost_instruments" int NOT NULL DEFAULT '0',
 "market_share_pct" numeric NOT NULL DEFAULT '0.00',
 "by_city" text DEFAULT NULL,
 "by_type" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_competitor_snapshots_tenant_id_period_start_index" ON "inmetro_competitor_snapshots" ("tenant_id","period_start");
CREATE INDEX "inmetro_competitor_snapshots_competitor_id_period_start_index" ON "inmetro_competitor_snapshots" ("competitor_id","period_start");
CREATE INDEX "inmetro_competitor_snapshots_tenant_id_idx" ON "inmetro_competitor_snapshots" ("tenant_id");

CREATE TABLE "inmetro_competitors" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "cnpj" varchar(20) DEFAULT NULL,
 "authorization_number" varchar(30) DEFAULT NULL,
 "phone" varchar(20) DEFAULT NULL,
 "email" varchar(255) DEFAULT NULL,
 "address" varchar(255) DEFAULT NULL,
 "city" varchar(255) NOT NULL,
 "state" varchar(2) NOT NULL DEFAULT 'MT',
 "authorized_species" text DEFAULT NULL,
 "mechanics" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "max_capacity" varchar(50) DEFAULT NULL,
 "accuracy_classes" text DEFAULT NULL,
 "authorization_valid_until" date DEFAULT NULL,
 "total_repairs_done" int NOT NULL DEFAULT '0',
 "last_repair_date" date DEFAULT NULL,
 "website" varchar(255) DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_competitors_city_state_index" ON "inmetro_competitors" ("city","state");
CREATE INDEX "inmetro_competitors_tid_idx" ON "inmetro_competitors" ("tenant_id");
CREATE INDEX "inmetro_competitors_tenant_id_idx" ON "inmetro_competitors" ("tenant_id");
CREATE INDEX "inmetro_competitors_deleted_at_idx" ON "inmetro_competitors" ("deleted_at");

CREATE TABLE "inmetro_compliance_checklists" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "instrument_type" varchar(100) NOT NULL,
 "regulation_reference" varchar(100) DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "items" text NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_compliance_checklists_tenant_id_instrument_type_index" ON "inmetro_compliance_checklists" ("tenant_id","instrument_type");
CREATE INDEX "inmetro_compliance_checklists_tenant_id_idx" ON "inmetro_compliance_checklists" ("tenant_id");

CREATE TABLE "inmetro_history" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "instrument_id" integer NOT NULL,
 "event_type" varchar NOT NULL DEFAULT 'verification',
 "event_date" date NOT NULL,
 "result" varchar NOT NULL DEFAULT 'approved',
 "executor" varchar(255) DEFAULT NULL,
 "executor_document" varchar(20) DEFAULT NULL,
 "validity_date" date DEFAULT NULL,
 "notes" text,
 "osint_threat_level" varchar(50) DEFAULT NULL,
 "source" varchar(30) NOT NULL DEFAULT 'psie_import',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "competitor_id" integer DEFAULT NULL
);
CREATE INDEX "inmetro_history_instrument_id_foreign" ON "inmetro_history" ("instrument_id");
CREATE INDEX "inmetro_history_event_date_index" ON "inmetro_history" ("event_date");
CREATE INDEX "inmetro_history_competitor_id_foreign" ON "inmetro_history" ("competitor_id");
CREATE INDEX "inmetro_history_tenant_id_idx" ON "inmetro_history" ("tenant_id");

CREATE TABLE "inmetro_instruments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "location_id" integer NOT NULL,
 "inmetro_number" varchar(30) NOT NULL,
 "serial_number" varchar(50) DEFAULT NULL,
 "brand" varchar(50) DEFAULT NULL,
 "model" varchar(50) DEFAULT NULL,
 "capacity" varchar(30) DEFAULT NULL,
 "instrument_type" varchar(80) NOT NULL DEFAULT 'Balança',
 "current_status" varchar NOT NULL DEFAULT 'unknown',
 "last_verification_at" date DEFAULT NULL,
 "next_verification_at" date DEFAULT NULL,
 "last_executor" varchar(255) DEFAULT NULL,
 "source" varchar(30) NOT NULL DEFAULT 'xml_import',
 "last_scrape_status" varchar(50) DEFAULT NULL,
 "next_deep_scrape_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "linked_equipment_id" integer DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL,
 "type" varchar(50) DEFAULT NULL
);
CREATE INDEX "inmetro_instruments_location_id_foreign" ON "inmetro_instruments" ("location_id");
CREATE INDEX "inmetro_instruments_next_verification_at_index" ON "inmetro_instruments" ("next_verification_at");
CREATE INDEX "inmetro_instruments_inmetro_number_index" ON "inmetro_instruments" ("inmetro_number");
CREATE INDEX "inmetro_instruments_linked_equipment_id_index" ON "inmetro_instruments" ("linked_equipment_id");
CREATE INDEX "inmetro_instruments_tenant_id_idx" ON "inmetro_instruments" ("tenant_id");

CREATE TABLE "inmetro_lead_interactions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "owner_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "channel" varchar(30) NOT NULL,
 "result" varchar(30) NOT NULL,
 "notes" text,
 "next_follow_up_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_lead_interactions_user_id_foreign" ON "inmetro_lead_interactions" ("user_id");
CREATE INDEX "inmetro_lead_interactions_owner_id_created_at_index" ON "inmetro_lead_interactions" ("owner_id","created_at");
CREATE INDEX "inmetro_lead_interactions_tenant_id_created_at_index" ON "inmetro_lead_interactions" ("tenant_id","created_at");
CREATE INDEX "inmetro_lead_interactions_tenant_id_idx" ON "inmetro_lead_interactions" ("tenant_id");

CREATE TABLE "inmetro_lead_scores" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "owner_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "total_score" tinyint NOT NULL DEFAULT '0',
 "expiration_score" tinyint NOT NULL DEFAULT '0',
 "value_score" tinyint NOT NULL DEFAULT '0',
 "contact_score" tinyint NOT NULL DEFAULT '0',
 "region_score" tinyint NOT NULL DEFAULT '0',
 "instrument_score" tinyint NOT NULL DEFAULT '0',
 "factors" text DEFAULT NULL,
 "calculated_at" datetime NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "inmetro_lead_scores_owner_id_unique" ON "inmetro_lead_scores" ("owner_id");
CREATE INDEX "inmetro_lead_scores_tenant_id_total_score_index" ON "inmetro_lead_scores" ("tenant_id","total_score");
CREATE INDEX "inmetro_lead_scores_tenant_id_idx" ON "inmetro_lead_scores" ("tenant_id");

CREATE TABLE "inmetro_locations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "owner_id" integer NOT NULL,
 "state_registration" varchar(30) DEFAULT NULL,
 "farm_name" varchar(255) DEFAULT NULL,
 "address_street" varchar(255) DEFAULT NULL,
 "address_number" varchar(20) DEFAULT NULL,
 "address_complement" varchar(255) DEFAULT NULL,
 "address_neighborhood" varchar(255) DEFAULT NULL,
 "address_city" varchar(255) NOT NULL,
 "address_state" varchar(2) NOT NULL DEFAULT 'MT',
 "address_zip" varchar(10) DEFAULT NULL,
 "phone_local" varchar(20) DEFAULT NULL,
 "email_local" varchar(255) DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "distance_from_base_km" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "inmetro_locations_owner_id_foreign" ON "inmetro_locations" ("owner_id");
CREATE INDEX "inmetro_locations_address_city_address_state_index" ON "inmetro_locations" ("address_city","address_state");
CREATE INDEX "inmetro_locations_tenant_id_idx" ON "inmetro_locations" ("tenant_id");

CREATE TABLE "inmetro_owners" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "document" varchar(20) NOT NULL,
 "name" varchar(255) NOT NULL,
 "trade_name" varchar(255) DEFAULT NULL,
 "type" varchar NOT NULL DEFAULT 'PJ',
 "phone" varchar(20) DEFAULT NULL,
 "phone2" varchar(20) DEFAULT NULL,
 "email" varchar(255) DEFAULT NULL,
 "contact_source" varchar(255) DEFAULT NULL,
 "contact_enriched_at" datetime NULL DEFAULT NULL,
 "lead_status" varchar NOT NULL DEFAULT 'new',
 "priority" varchar(20) NOT NULL DEFAULT 'normal',
 "converted_to_customer_id" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "estimated_revenue" numeric NOT NULL DEFAULT '0.00',
 "total_instruments" int NOT NULL DEFAULT '0',
 "lead_score" tinyint NOT NULL DEFAULT '0',
 "segment" varchar(50) DEFAULT NULL,
 "cnpj_root" varchar(8) DEFAULT NULL,
 "last_contacted_at" datetime NULL DEFAULT NULL,
 "contact_count" int NOT NULL DEFAULT '0',
 "next_contact_at" datetime NULL DEFAULT NULL,
 "churn_risk" tinyint NOT NULL DEFAULT '0',
 "state" varchar(2) DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "enrichment_data" text DEFAULT NULL
);
CREATE UNIQUE INDEX "inmetro_owners_tenant_id_document_unique" ON "inmetro_owners" ("tenant_id","document");
CREATE INDEX "inmetro_owners_converted_to_customer_id_foreign" ON "inmetro_owners" ("converted_to_customer_id");
CREATE INDEX "inmetro_owners_document_index" ON "inmetro_owners" ("document");
CREATE INDEX "inmetro_owners_lead_score_index" ON "inmetro_owners" ("lead_score");
CREATE INDEX "inmetro_owners_segment_index" ON "inmetro_owners" ("segment");
CREATE INDEX "inmetro_owners_cnpj_root_index" ON "inmetro_owners" ("cnpj_root");
CREATE INDEX "inmetro_owners_churn_risk_index" ON "inmetro_owners" ("churn_risk");
CREATE INDEX "inmetro_owners_deleted_at_idx" ON "inmetro_owners" ("deleted_at");

CREATE TABLE "inmetro_prospection_queue" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "owner_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "assigned_to" integer DEFAULT NULL,
 "queue_date" date NOT NULL,
 "position" tinyint NOT NULL DEFAULT '0',
 "reason" varchar(100) NOT NULL,
 "suggested_script" text,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "contacted_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_prospection_queue_owner_id_foreign" ON "inmetro_prospection_queue" ("owner_id");
CREATE INDEX "inmetro_prospection_queue_tenant_id_queue_date_position_index" ON "inmetro_prospection_queue" ("tenant_id","queue_date","position");
CREATE INDEX "inmetro_prospection_queue_assigned_to_queue_date_index" ON "inmetro_prospection_queue" ("assigned_to","queue_date");
CREATE INDEX "inmetro_prospection_queue_tenant_id_idx" ON "inmetro_prospection_queue" ("tenant_id");

CREATE TABLE "inmetro_seal_statuses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "inmetro_seal_statuses_tid_slug_uq" ON "inmetro_seal_statuses" ("tenant_id","slug");
CREATE INDEX "inmetro_seal_statuses_tid_idx" ON "inmetro_seal_statuses" ("tenant_id");
CREATE INDEX "inmetro_seal_statuses_del_idx" ON "inmetro_seal_statuses" ("deleted_at");
CREATE INDEX "inmetro_seal_statuses_deleted_at_idx" ON "inmetro_seal_statuses" ("deleted_at");

CREATE TABLE "inmetro_seal_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "inmetro_seal_types_tid_slug_uq" ON "inmetro_seal_types" ("tenant_id","slug");
CREATE INDEX "inmetro_seal_types_tid_idx" ON "inmetro_seal_types" ("tenant_id");
CREATE INDEX "inmetro_seal_types_del_idx" ON "inmetro_seal_types" ("deleted_at");
CREATE INDEX "inmetro_seal_types_deleted_at_idx" ON "inmetro_seal_types" ("deleted_at");

CREATE TABLE "inmetro_seals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "batch_id" integer DEFAULT NULL,
 "type" varchar NOT NULL,
 "number" varchar(30) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'available',
 "assigned_to" integer DEFAULT NULL,
 "assigned_at" datetime NULL DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "equipment_id" integer DEFAULT NULL,
 "photo_path" varchar(255) DEFAULT NULL,
 "used_at" datetime NULL DEFAULT NULL,
 "notes" text,
 "psei_status" varchar(20) NOT NULL DEFAULT 'not_applicable',
 "psei_submitted_at" datetime NULL DEFAULT NULL,
 "psei_protocol" varchar(50) DEFAULT NULL,
 "deadline_at" datetime NULL DEFAULT NULL,
 "deadline_status" varchar(15) NOT NULL DEFAULT 'ok',
 "returned_at" datetime NULL DEFAULT NULL,
 "returned_reason" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "inmetro_seals_tenant_id_type_number_unique" ON "inmetro_seals" ("tenant_id","type","number");
CREATE INDEX "inmetro_seals_work_order_id_foreign" ON "inmetro_seals" ("work_order_id");
CREATE INDEX "inmetro_seals_equipment_id_foreign" ON "inmetro_seals" ("equipment_id");
CREATE INDEX "inmetro_seals_tenant_id_status_type_index" ON "inmetro_seals" ("tenant_id","status","type");
CREATE INDEX "inmetro_seals_assigned_to_status_index" ON "inmetro_seals" ("assigned_to","status");
CREATE INDEX "inmetro_seals_del_idx" ON "inmetro_seals" ("deleted_at");
CREATE INDEX "inmetro_seals_batch_id_foreign" ON "inmetro_seals" ("batch_id");
CREATE INDEX "inmetro_seals_idx_seals_psei_status" ON "inmetro_seals" ("tenant_id","psei_status");
CREATE INDEX "inmetro_seals_idx_seals_deadline_status" ON "inmetro_seals" ("tenant_id","deadline_status");
CREATE INDEX "inmetro_seals_idx_seals_batch" ON "inmetro_seals" ("tenant_id","batch_id");
CREATE INDEX "inmetro_seals_deleted_at_idx" ON "inmetro_seals" ("deleted_at");

CREATE TABLE "inmetro_snapshots" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "competitor_id" integer NOT NULL,
 "data" text DEFAULT NULL,
 "captured_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_snapshots_imsnap_tenant_comp_idx" ON "inmetro_snapshots" ("tenant_id","competitor_id");
CREATE INDEX "inmetro_snapshots_tenant_id_idx" ON "inmetro_snapshots" ("tenant_id");

CREATE TABLE "inmetro_webhooks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "event_type" varchar(50) NOT NULL,
 "url" varchar(500) NOT NULL,
 "secret" varchar(100) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "failure_count" int NOT NULL DEFAULT '0',
 "last_triggered_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_webhooks_tenant_id_event_type_index" ON "inmetro_webhooks" ("tenant_id","event_type");
CREATE INDEX "inmetro_webhooks_tenant_id_idx" ON "inmetro_webhooks" ("tenant_id");

CREATE TABLE "inmetro_win_loss" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "owner_id" integer DEFAULT NULL,
 "competitor_id" integer DEFAULT NULL,
 "outcome" varchar(10) NOT NULL,
 "reason" varchar(100) DEFAULT NULL,
 "estimated_value" numeric DEFAULT NULL,
 "notes" text,
 "outcome_date" date NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inmetro_win_loss_owner_id_foreign" ON "inmetro_win_loss" ("owner_id");
CREATE INDEX "inmetro_win_loss_tenant_id_outcome_date_index" ON "inmetro_win_loss" ("tenant_id","outcome_date");
CREATE INDEX "inmetro_win_loss_competitor_id_outcome_index" ON "inmetro_win_loss" ("competitor_id","outcome");
CREATE INDEX "inmetro_win_loss_tenant_id_idx" ON "inmetro_win_loss" ("tenant_id");

CREATE TABLE "inss_brackets" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "year" int NOT NULL,
 "min_salary" numeric NOT NULL,
 "max_salary" numeric NOT NULL,
 "rate" numeric NOT NULL,
 "deduction" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "inss_brackets_year_min_salary_unique" ON "inss_brackets" ("year","min_salary");

CREATE TABLE "inventories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "warehouse_id" integer NOT NULL,
 "reference" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'open',
 "created_by" integer NOT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inventories_warehouse_id_foreign" ON "inventories" ("warehouse_id");
CREATE INDEX "inventories_created_by_foreign" ON "inventories" ("created_by");
CREATE INDEX "inventories_tid_idx" ON "inventories" ("tenant_id");
CREATE INDEX "inventories_del_idx" ON "inventories" ("deleted_at");
CREATE INDEX "inventories_tid_st_idx" ON "inventories" ("tenant_id","status");
CREATE INDEX "inventories_tenant_id_idx" ON "inventories" ("tenant_id");
CREATE INDEX "inventories_deleted_at_idx" ON "inventories" ("deleted_at");

CREATE TABLE "inventory_count_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "inventory_count_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "system_quantity" numeric NOT NULL,
 "counted_quantity" numeric DEFAULT NULL,
 "counted_by" integer DEFAULT NULL,
 "counted_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inventory_count_items_inventory_count_id_foreign" ON "inventory_count_items" ("inventory_count_id");
CREATE INDEX "inventory_count_items_product_id_foreign" ON "inventory_count_items" ("product_id");
CREATE INDEX "inventory_count_items_counted_by_foreign" ON "inventory_count_items" ("counted_by");

CREATE TABLE "inventory_counts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "warehouse_id" integer NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'in_progress',
 "started_by" integer NOT NULL,
 "items_count" int NOT NULL DEFAULT '0',
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inventory_counts_warehouse_id_foreign" ON "inventory_counts" ("warehouse_id");
CREATE INDEX "inventory_counts_started_by_foreign" ON "inventory_counts" ("started_by");
CREATE INDEX "inventory_counts_tid_idx" ON "inventory_counts" ("tenant_id");
CREATE INDEX "inventory_counts_tid_st_idx" ON "inventory_counts" ("tenant_id","status");
CREATE INDEX "inventory_counts_tenant_id_idx" ON "inventory_counts" ("tenant_id");

CREATE TABLE "inventory_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "inventory_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "batch_id" integer DEFAULT NULL,
 "product_serial_id" integer DEFAULT NULL,
 "expected_quantity" numeric NOT NULL,
 "counted_quantity" numeric DEFAULT NULL,
 "adjustment_quantity" numeric DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE INDEX "inventory_items_inventory_id_foreign" ON "inventory_items" ("inventory_id");
CREATE INDEX "inventory_items_product_id_foreign" ON "inventory_items" ("product_id");
CREATE INDEX "inventory_items_batch_id_foreign" ON "inventory_items" ("batch_id");
CREATE INDEX "inventory_items_product_serial_id_foreign" ON "inventory_items" ("product_serial_id");
CREATE INDEX "inventory_items_inv_items_tenant_idx" ON "inventory_items" ("tenant_id");
CREATE INDEX "inventory_items_tenant_id_idx" ON "inventory_items" ("tenant_id");

CREATE TABLE "inventory_tables_v3" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "inventory_tables_v3_tenant_id_idx" ON "inventory_tables_v3" ("tenant_id");

CREATE TABLE "invoices" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "customer_id" integer NOT NULL,
 "created_by" integer NOT NULL,
 "invoice_number" varchar(50) NOT NULL,
 "nf_number" varchar(50) DEFAULT NULL,
 "status" varchar NOT NULL DEFAULT 'draft',
 "fiscal_status" varchar(255) DEFAULT NULL,
 "fiscal_note_key" varchar(255) DEFAULT NULL,
 "fiscal_emitted_at" datetime NULL DEFAULT NULL,
 "fiscal_error" text,
 "total" numeric NOT NULL,
 "discount" numeric NOT NULL DEFAULT '0.00',
 "issued_at" date DEFAULT NULL,
 "due_date" date DEFAULT NULL,
 "observations" text,
 "items" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "invoices_tenant_id_invoice_number_unique" ON "invoices" ("tenant_id","invoice_number");
CREATE INDEX "invoices_created_by_foreign" ON "invoices" ("created_by");
CREATE INDEX "invoices_inv_tenant_status" ON "invoices" ("tenant_id","status");
CREATE INDEX "invoices_inv_tenant_customer" ON "invoices" ("tenant_id","customer_id");
CREATE INDEX "invoices_inv_tenant_due" ON "invoices" ("tenant_id","due_date");
CREATE INDEX "invoices_inv_deleted_at" ON "invoices" ("tenant_id","deleted_at");
CREATE INDEX "invoices_del_idx" ON "invoices" ("deleted_at");
CREATE INDEX "invoices_work_order_id_fk_idx" ON "invoices" ("work_order_id");
CREATE INDEX "invoices_customer_id_fk_idx" ON "invoices" ("customer_id");
CREATE INDEX "invoices_tid_st_idx" ON "invoices" ("tenant_id","status");
CREATE INDEX "invoices_deleted_at_idx" ON "invoices" ("deleted_at");

CREATE TABLE "irrf_brackets" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "year" int NOT NULL,
 "min_base" numeric NOT NULL,
 "max_base" numeric DEFAULT NULL,
 "rate" numeric NOT NULL,
 "deduction" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "irrf_brackets_year_min_base_unique" ON "irrf_brackets" ("year","min_base");

CREATE TABLE "job_batches" (
 "id" varchar(255) NOT NULL,
 "name" varchar(255) NOT NULL,
 "total_jobs" int NOT NULL,
 "pending_jobs" int NOT NULL,
 "failed_jobs" int NOT NULL,
 "failed_job_ids" text NOT NULL,
 "options" text,
 "cancelled_at" int DEFAULT NULL,
 "created_at" int NOT NULL,
 "finished_at" int DEFAULT NULL,
 PRIMARY KEY ("id")
);

CREATE TABLE "job_postings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "department_id" integer DEFAULT NULL,
 "position_id" integer DEFAULT NULL,
 "description" text NOT NULL,
 "requirements" text,
 "salary_range_min" numeric DEFAULT NULL,
 "salary_range_max" numeric DEFAULT NULL,
 "status" varchar NOT NULL DEFAULT 'open',
 "opened_at" datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 "closed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "job_postings_department_id_foreign" ON "job_postings" ("department_id");
CREATE INDEX "job_postings_position_id_foreign" ON "job_postings" ("position_id");
CREATE INDEX "job_postings_tid_idx" ON "job_postings" ("tenant_id");
CREATE INDEX "job_postings_tid_st_idx" ON "job_postings" ("tenant_id","status");
CREATE INDEX "job_postings_tenant_id_idx" ON "job_postings" ("tenant_id");

CREATE TABLE "jobs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "queue" varchar(255) NOT NULL,
 "payload" text NOT NULL,
 "attempts" tinyint NOT NULL,
 "reserved_at" int DEFAULT NULL,
 "available_at" int NOT NULL,
 "created_at" int NOT NULL
);
CREATE INDEX "jobs_queue_index" ON "jobs" ("queue");

CREATE TABLE "journey_approvals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "journey_day_id" integer DEFAULT NULL,
 "level" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "approver_id" integer DEFAULT NULL,
 "decided_at" datetime NULL DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "journey_entry_id" integer DEFAULT NULL
);
CREATE INDEX "journey_approvals_journey_day_id_foreign" ON "journey_approvals" ("journey_day_id");
CREATE INDEX "journey_approvals_approver_id_foreign" ON "journey_approvals" ("approver_id");
CREATE INDEX "journey_approvals_tenant_id_journey_day_id_level_index" ON "journey_approvals" ("tenant_id","journey_day_id","level");
CREATE INDEX "journey_approvals_tenant_id_status_index" ON "journey_approvals" ("tenant_id","status");
CREATE INDEX "journey_approvals_journey_entry_id_foreign" ON "journey_approvals" ("journey_entry_id");

CREATE TABLE "journey_blocks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "journey_day_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "classification" varchar(255) NOT NULL,
 "started_at" datetime NOT NULL,
 "ended_at" datetime NULL DEFAULT NULL,
 "duration_minutes" int DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "time_clock_entry_id" integer DEFAULT NULL,
 "fleet_trip_id" integer DEFAULT NULL,
 "schedule_id" integer DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "source" varchar(255) NOT NULL,
 "is_auto_classified" tinyint NOT NULL DEFAULT '1',
 "is_manually_adjusted" tinyint NOT NULL DEFAULT '0',
 "adjusted_by" integer DEFAULT NULL,
 "adjustment_reason" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "journey_entry_id" integer DEFAULT NULL
);
CREATE INDEX "journey_blocks_user_id_foreign" ON "journey_blocks" ("user_id");
CREATE INDEX "journey_blocks_work_order_id_foreign" ON "journey_blocks" ("work_order_id");
CREATE INDEX "journey_blocks_time_clock_entry_id_foreign" ON "journey_blocks" ("time_clock_entry_id");
CREATE INDEX "journey_blocks_fleet_trip_id_foreign" ON "journey_blocks" ("fleet_trip_id");
CREATE INDEX "journey_blocks_schedule_id_foreign" ON "journey_blocks" ("schedule_id");
CREATE INDEX "journey_blocks_adjusted_by_foreign" ON "journey_blocks" ("adjusted_by");
CREATE INDEX "journey_blocks_tenant_id_user_id_started_at_index" ON "journey_blocks" ("tenant_id","user_id","started_at");
CREATE INDEX "journey_blocks_journey_day_id_classification_index" ON "journey_blocks" ("journey_day_id","classification");
CREATE INDEX "journey_blocks_journey_entry_id_foreign" ON "journey_blocks" ("journey_entry_id");
CREATE INDEX "journey_blocks_deleted_at_idx" ON "journey_blocks" ("deleted_at");

CREATE TABLE "journey_days" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "reference_date" date NOT NULL,
 "regime_type" varchar(255) NOT NULL DEFAULT 'clt_mensal',
 "total_minutes_worked" int NOT NULL DEFAULT '0',
 "total_minutes_overtime" int NOT NULL DEFAULT '0',
 "total_minutes_travel" int NOT NULL DEFAULT '0',
 "total_minutes_wait" int NOT NULL DEFAULT '0',
 "total_minutes_break" int NOT NULL DEFAULT '0',
 "total_minutes_overnight" int NOT NULL DEFAULT '0',
 "total_minutes_oncall" int NOT NULL DEFAULT '0',
 "operational_approval_status" varchar(255) NOT NULL DEFAULT 'pending',
 "operational_approver_id" integer DEFAULT NULL,
 "operational_approved_at" datetime NULL DEFAULT NULL,
 "hr_approval_status" varchar(255) NOT NULL DEFAULT 'pending',
 "hr_approver_id" integer DEFAULT NULL,
 "hr_approved_at" datetime NULL DEFAULT NULL,
 "is_closed" tinyint NOT NULL DEFAULT '0',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "journey_days_tenant_id_user_id_reference_date_unique" ON "journey_days" ("tenant_id","user_id","reference_date");
CREATE INDEX "journey_days_user_id_foreign" ON "journey_days" ("user_id");
CREATE INDEX "journey_days_operational_approver_id_foreign" ON "journey_days" ("operational_approver_id");
CREATE INDEX "journey_days_hr_approver_id_foreign" ON "journey_days" ("hr_approver_id");
CREATE INDEX "journey_days_tenant_id_reference_date_index" ON "journey_days" ("tenant_id","reference_date");
CREATE INDEX "journey_days_deleted_at_idx" ON "journey_days" ("deleted_at");

CREATE TABLE "journey_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "date" date NOT NULL,
 "journey_rule_id" integer DEFAULT NULL,
 "scheduled_hours" numeric NOT NULL DEFAULT '0.00',
 "worked_hours" numeric NOT NULL DEFAULT '0.00',
 "overtime_hours_50" numeric NOT NULL DEFAULT '0.00',
 "overtime_hours_100" numeric NOT NULL DEFAULT '0.00',
 "night_hours" numeric NOT NULL DEFAULT '0.00',
 "absence_hours" numeric NOT NULL DEFAULT '0.00',
 "hour_bank_balance" numeric NOT NULL DEFAULT '0.00',
 "overtime_limit_exceeded" tinyint NOT NULL DEFAULT '0',
 "tolerance_applied" tinyint NOT NULL DEFAULT '0',
 "break_compliance" varchar(20) DEFAULT NULL,
 "inter_shift_hours" numeric DEFAULT NULL,
 "is_holiday" tinyint NOT NULL DEFAULT '0',
 "is_dsr" tinyint NOT NULL DEFAULT '0',
 "status" varchar(30) NOT NULL DEFAULT 'calculated',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "total_minutes_worked" int NOT NULL DEFAULT '0',
 "total_minutes_overtime" int NOT NULL DEFAULT '0',
 "total_minutes_travel" int NOT NULL DEFAULT '0',
 "total_minutes_wait" int NOT NULL DEFAULT '0',
 "total_minutes_break" int NOT NULL DEFAULT '0',
 "total_minutes_overnight" int NOT NULL DEFAULT '0',
 "total_minutes_oncall" int NOT NULL DEFAULT '0',
 "operational_approval_status" varchar(255) NOT NULL DEFAULT 'pending',
 "operational_approver_id" integer DEFAULT NULL,
 "operational_approved_at" datetime NULL DEFAULT NULL,
 "hr_approval_status" varchar(255) NOT NULL DEFAULT 'pending',
 "hr_approver_id" integer DEFAULT NULL,
 "hr_approved_at" datetime NULL DEFAULT NULL,
 "is_closed" tinyint NOT NULL DEFAULT '0',
 "regime_type" varchar(255) NOT NULL DEFAULT 'clt_mensal',
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "journey_entries_user_id_date_unique" ON "journey_entries" ("user_id","date");
CREATE INDEX "journey_entries_journey_rule_id_foreign" ON "journey_entries" ("journey_rule_id");
CREATE INDEX "journey_entries_tid_idx" ON "journey_entries" ("tenant_id");
CREATE INDEX "journey_entries_operational_approver_id_foreign" ON "journey_entries" ("operational_approver_id");
CREATE INDEX "journey_entries_hr_approver_id_foreign" ON "journey_entries" ("hr_approver_id");
CREATE INDEX "journey_entries_deleted_at_idx" ON "journey_entries" ("deleted_at");
CREATE INDEX "journey_entries_tenant_id_idx" ON "journey_entries" ("tenant_id");

CREATE TABLE "journey_policies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "regime_type" varchar(255) NOT NULL DEFAULT 'clt_mensal',
 "daily_hours_limit" int NOT NULL DEFAULT '480',
 "weekly_hours_limit" int NOT NULL DEFAULT '2640',
 "monthly_hours_limit" int DEFAULT NULL,
 "break_minutes" int NOT NULL DEFAULT '60',
 "displacement_counts_as_work" tinyint NOT NULL DEFAULT '0',
 "wait_time_counts_as_work" tinyint NOT NULL DEFAULT '1',
 "travel_meal_counts_as_break" tinyint NOT NULL DEFAULT '1',
 "auto_suggest_clock_on_displacement" tinyint NOT NULL DEFAULT '1',
 "pre_assigned_break" tinyint NOT NULL DEFAULT '0',
 "overnight_min_hours" int NOT NULL DEFAULT '11',
 "oncall_multiplier_percent" int NOT NULL DEFAULT '33',
 "overtime_50_percent_limit" int DEFAULT NULL,
 "overtime_100_percent_limit" int DEFAULT NULL,
 "saturday_is_overtime" tinyint NOT NULL DEFAULT '0',
 "sunday_is_overtime" tinyint NOT NULL DEFAULT '1',
 "custom_rules" text DEFAULT NULL,
 "is_default" tinyint NOT NULL DEFAULT '0',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "journey_policies_tenant_id_is_active_index" ON "journey_policies" ("tenant_id","is_active");
CREATE INDEX "journey_policies_deleted_at_idx" ON "journey_policies" ("deleted_at");

CREATE TABLE "journey_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "daily_hours" numeric NOT NULL DEFAULT '8.00',
 "weekly_hours" numeric NOT NULL DEFAULT '44.00',
 "overtime_weekday_pct" int NOT NULL DEFAULT '50',
 "overtime_weekend_pct" int NOT NULL DEFAULT '100',
 "overtime_holiday_pct" int NOT NULL DEFAULT '100',
 "night_shift_pct" int NOT NULL DEFAULT '20',
 "night_start" time NOT NULL DEFAULT '22:00:00',
 "night_end" time NOT NULL DEFAULT '05:00:00',
 "uses_hour_bank" tinyint NOT NULL DEFAULT '0',
 "hour_bank_expiry_months" int NOT NULL DEFAULT '6',
 "allow_negative_hour_bank_deduction" tinyint NOT NULL DEFAULT '0',
 "agreement_type" varchar(20) NOT NULL DEFAULT 'individual',
 "is_default" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "daily_hours_limit" int NOT NULL DEFAULT '480',
 "weekly_hours_limit" int NOT NULL DEFAULT '2640',
 "monthly_hours_limit" int DEFAULT NULL,
 "break_minutes" int NOT NULL DEFAULT '60',
 "displacement_counts_as_work" tinyint NOT NULL DEFAULT '0',
 "wait_time_counts_as_work" tinyint NOT NULL DEFAULT '1',
 "travel_meal_counts_as_break" tinyint NOT NULL DEFAULT '1',
 "auto_suggest_clock_on_displacement" tinyint NOT NULL DEFAULT '1',
 "pre_assigned_break" tinyint NOT NULL DEFAULT '0',
 "overnight_min_hours" int NOT NULL DEFAULT '11',
 "oncall_multiplier_percent" int NOT NULL DEFAULT '33',
 "overtime_50_percent_limit" int DEFAULT NULL,
 "overtime_100_percent_limit" int DEFAULT NULL,
 "saturday_is_overtime" tinyint NOT NULL DEFAULT '0',
 "sunday_is_overtime" tinyint NOT NULL DEFAULT '1',
 "custom_rules" text DEFAULT NULL,
 "regime_type" varchar(255) NOT NULL DEFAULT 'clt_mensal',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "compensation_period_days" int NOT NULL DEFAULT '30',
 "max_positive_balance_minutes" int DEFAULT NULL,
 "max_negative_balance_minutes" int DEFAULT NULL,
 "block_on_negative_exceeded" tinyint NOT NULL DEFAULT '1',
 "auto_compensate" tinyint NOT NULL DEFAULT '0',
 "convert_expired_to_payment" tinyint NOT NULL DEFAULT '0',
 "overtime_50_multiplier" numeric NOT NULL DEFAULT '1.50',
 "overtime_100_multiplier" numeric NOT NULL DEFAULT '2.00',
 "applicable_roles" text DEFAULT NULL,
 "applicable_teams" text DEFAULT NULL,
 "applicable_unions" text DEFAULT NULL,
 "requires_two_level_approval" tinyint NOT NULL DEFAULT '1',
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "journey_rules_tid_idx" ON "journey_rules" ("tenant_id");
CREATE INDEX "journey_rules_tenant_id_idx" ON "journey_rules" ("tenant_id");
CREATE INDEX "journey_rules_deleted_at_idx" ON "journey_rules" ("deleted_at");

CREATE TABLE "kiosk_sessions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "device_identifier" varchar(100) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'active',
 "allowed_pages" text DEFAULT NULL,
 "started_at" datetime NOT NULL,
 "last_activity_at" datetime NULL DEFAULT NULL,
 "ended_at" datetime NULL DEFAULT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "user_agent" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "kiosk_sessions_user_id_foreign" ON "kiosk_sessions" ("user_id");
CREATE INDEX "kiosk_sessions_tenant_id_status_index" ON "kiosk_sessions" ("tenant_id","status");
CREATE INDEX "kiosk_sessions_device_identifier_status_index" ON "kiosk_sessions" ("device_identifier","status");
CREATE INDEX "kiosk_sessions_tenant_id_idx" ON "kiosk_sessions" ("tenant_id");

CREATE TABLE "knowledge_base_articles" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "content" text NOT NULL,
 "category" varchar(100) NOT NULL,
 "published" tinyint NOT NULL DEFAULT '0',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "knowledge_base_articles_tenant_id_index" ON "knowledge_base_articles" ("tenant_id");
CREATE INDEX "knowledge_base_articles_category_index" ON "knowledge_base_articles" ("category");
CREATE INDEX "knowledge_base_articles_tenant_id_idx" ON "knowledge_base_articles" ("tenant_id");

CREATE TABLE "lab_logbook_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "entry_date" date NOT NULL,
 "type" varchar(255) NOT NULL,
 "description" text NOT NULL,
 "temperature" numeric DEFAULT NULL,
 "humidity" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "lab_logbook_entries_user_id_foreign" ON "lab_logbook_entries" ("user_id");
CREATE INDEX "lab_logbook_entries_tid_idx" ON "lab_logbook_entries" ("tenant_id");
CREATE INDEX "lab_logbook_entries_tenant_id_idx" ON "lab_logbook_entries" ("tenant_id");

CREATE TABLE "lead_sources" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "lead_sources_tid_slug_uq" ON "lead_sources" ("tenant_id","slug");
CREATE INDEX "lead_sources_tid_idx" ON "lead_sources" ("tenant_id");
CREATE INDEX "lead_sources_del_idx" ON "lead_sources" ("deleted_at");
CREATE INDEX "lead_sources_deleted_at_idx" ON "lead_sources" ("deleted_at");

CREATE TABLE "leave_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "type" varchar(30) NOT NULL,
 "start_date" date NOT NULL,
 "end_date" date NOT NULL,
 "days_count" int NOT NULL,
 "reason" text,
 "document_path" varchar(255) DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'pending',
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "rejection_reason" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "leave_requests_approved_by_foreign" ON "leave_requests" ("approved_by");
CREATE INDEX "leave_requests_tenant_id_status_index" ON "leave_requests" ("tenant_id","status");
CREATE INDEX "leave_requests_lr_tenant_user_status" ON "leave_requests" ("tenant_id","user_id","status");
CREATE INDEX "leave_requests_user_id_fk_idx" ON "leave_requests" ("user_id");
CREATE INDEX "leave_requests_tenant_id_idx" ON "leave_requests" ("tenant_id");

CREATE TABLE "lgpd_anonymization_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "entity_type" varchar(255) NOT NULL,
 "entity_id" integer NOT NULL,
 "holder_document" varchar(255) NOT NULL,
 "anonymized_fields" text NOT NULL,
 "legal_basis" varchar(255) NOT NULL,
 "executed_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "lgpd_anonymization_logs_executed_by_foreign" ON "lgpd_anonymization_logs" ("executed_by");
CREATE INDEX "lgpd_anonymization_logs_entity_type_entity_id_index" ON "lgpd_anonymization_logs" ("entity_type","entity_id");
CREATE INDEX "lgpd_anonymization_logs_tenant_id_idx" ON "lgpd_anonymization_logs" ("tenant_id");

CREATE TABLE "lgpd_consent_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "holder_type" varchar(255) NOT NULL,
 "holder_id" integer NOT NULL,
 "holder_name" varchar(255) NOT NULL,
 "holder_email" varchar(255) DEFAULT NULL,
 "holder_document" varchar(255) DEFAULT NULL,
 "purpose" varchar(255) NOT NULL,
 "legal_basis" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'granted',
 "granted_at" datetime NULL DEFAULT NULL,
 "revoked_at" datetime NULL DEFAULT NULL,
 "ip_address" varchar(255) DEFAULT NULL,
 "user_agent" varchar(255) DEFAULT NULL,
 "revocation_reason" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "lgpd_consent_logs_holder_type_holder_id_index" ON "lgpd_consent_logs" ("holder_type","holder_id");
CREATE INDEX "lgpd_consent_logs_tenant_id_idx" ON "lgpd_consent_logs" ("tenant_id");

CREATE TABLE "lgpd_data_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "protocol" varchar(255) NOT NULL,
 "holder_name" varchar(255) NOT NULL,
 "holder_email" varchar(255) NOT NULL,
 "holder_document" varchar(255) NOT NULL,
 "request_type" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "description" text,
 "response_notes" text,
 "response_file_path" varchar(255) DEFAULT NULL,
 "deadline" date NOT NULL,
 "responded_at" datetime NULL DEFAULT NULL,
 "responded_by" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "lgpd_data_requests_protocol_unique" ON "lgpd_data_requests" ("protocol");
CREATE INDEX "lgpd_data_requests_responded_by_foreign" ON "lgpd_data_requests" ("responded_by");
CREATE INDEX "lgpd_data_requests_created_by_foreign" ON "lgpd_data_requests" ("created_by");
CREATE INDEX "lgpd_data_requests_tenant_id_idx" ON "lgpd_data_requests" ("tenant_id");

CREATE TABLE "lgpd_data_treatments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "data_category" varchar(255) NOT NULL,
 "purpose" varchar(255) NOT NULL,
 "legal_basis" varchar(255) NOT NULL,
 "description" text,
 "data_types" varchar(255) NOT NULL,
 "retention_period" varchar(255) DEFAULT NULL,
 "retention_legal_basis" varchar(255) DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "lgpd_data_treatments_created_by_foreign" ON "lgpd_data_treatments" ("created_by");
CREATE INDEX "lgpd_data_treatments_tenant_id_idx" ON "lgpd_data_treatments" ("tenant_id");

CREATE TABLE "lgpd_dpo_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "dpo_name" varchar(255) NOT NULL,
 "dpo_email" varchar(255) NOT NULL,
 "dpo_phone" varchar(255) DEFAULT NULL,
 "is_public" tinyint NOT NULL DEFAULT '1',
 "updated_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "lgpd_dpo_configs_tenant_id_unique" ON "lgpd_dpo_configs" ("tenant_id");
CREATE INDEX "lgpd_dpo_configs_updated_by_foreign" ON "lgpd_dpo_configs" ("updated_by");

CREATE TABLE "lgpd_security_incidents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "protocol" varchar(255) NOT NULL,
 "severity" varchar(255) NOT NULL,
 "description" text NOT NULL,
 "affected_data" text NOT NULL,
 "affected_holders_count" int NOT NULL DEFAULT '0',
 "measures_taken" text,
 "anpd_notification" text,
 "holders_notified" tinyint NOT NULL DEFAULT '0',
 "holders_notified_at" datetime NULL DEFAULT NULL,
 "detected_at" datetime NOT NULL,
 "anpd_reported_at" datetime NULL DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'open',
 "reported_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "lgpd_security_incidents_protocol_unique" ON "lgpd_security_incidents" ("protocol");
CREATE INDEX "lgpd_security_incidents_reported_by_foreign" ON "lgpd_security_incidents" ("reported_by");
CREATE INDEX "lgpd_security_incidents_tenant_id_idx" ON "lgpd_security_incidents" ("tenant_id");

CREATE TABLE "linearity_tests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_calibration_id" integer NOT NULL,
 "point_order" int NOT NULL,
 "reference_value" numeric NOT NULL,
 "unit" varchar(20) NOT NULL DEFAULT 'g',
 "indication_increasing" numeric DEFAULT NULL,
 "indication_decreasing" numeric DEFAULT NULL,
 "error_increasing" numeric DEFAULT NULL,
 "error_decreasing" numeric DEFAULT NULL,
 "hysteresis" numeric DEFAULT NULL,
 "max_permissible_error" numeric DEFAULT NULL,
 "conforms" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "linearity_tests_equipment_calibration_id_foreign" ON "linearity_tests" ("equipment_calibration_id");
CREATE INDEX "linearity_tests_tenant_id_equipment_calibration_id_index" ON "linearity_tests" ("tenant_id","equipment_calibration_id");

CREATE TABLE "maintenance_reports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "equipment_id" integer NOT NULL,
 "performed_by" integer DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "defect_found" text NOT NULL,
 "probable_cause" text,
 "corrective_action" text,
 "parts_replaced" text DEFAULT NULL,
 "seal_status" varchar(30) DEFAULT NULL,
 "new_seal_number" varchar(50) DEFAULT NULL,
 "condition_before" varchar(30) NOT NULL DEFAULT 'defective',
 "condition_after" varchar(30) NOT NULL DEFAULT 'functional',
 "requires_calibration_after" tinyint NOT NULL DEFAULT '1',
 "requires_ipem_verification" tinyint NOT NULL DEFAULT '0',
 "notes" text,
 "photo_evidence" text DEFAULT NULL,
 "started_at" datetime DEFAULT NULL,
 "completed_at" datetime DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "maintenance_reports_work_order_id_foreign" ON "maintenance_reports" ("work_order_id");
CREATE INDEX "maintenance_reports_equipment_id_foreign" ON "maintenance_reports" ("equipment_id");
CREATE INDEX "maintenance_reports_performed_by_foreign" ON "maintenance_reports" ("performed_by");
CREATE INDEX "maintenance_reports_approved_by_foreign" ON "maintenance_reports" ("approved_by");
CREATE INDEX "maintenance_reports_tenant_id_work_order_id_index" ON "maintenance_reports" ("tenant_id","work_order_id");
CREATE INDEX "maintenance_reports_tenant_id_equipment_id_index" ON "maintenance_reports" ("tenant_id","equipment_id");

CREATE TABLE "maintenance_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "maintenance_types_tid_slug_uq" ON "maintenance_types" ("tenant_id","slug");
CREATE INDEX "maintenance_types_tid_idx" ON "maintenance_types" ("tenant_id");
CREATE INDEX "maintenance_types_del_idx" ON "maintenance_types" ("deleted_at");
CREATE INDEX "maintenance_types_deleted_at_idx" ON "maintenance_types" ("deleted_at");

CREATE TABLE "management_review_actions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "management_review_id" integer NOT NULL,
 "description" varchar(255) NOT NULL,
 "responsible_id" integer DEFAULT NULL,
 "due_date" date DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'pending',
 "completed_at" date DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "management_review_actions_responsible_id_foreign" ON "management_review_actions" ("responsible_id");
CREATE INDEX "management_review_actions_mgmt_rev_actions_review_idx" ON "management_review_actions" ("management_review_id","status");

CREATE TABLE "management_reviews" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "meeting_date" date NOT NULL,
 "title" varchar(255) NOT NULL,
 "participants" text,
 "agenda" text,
 "decisions" text,
 "summary" text,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "management_reviews_created_by_foreign" ON "management_reviews" ("created_by");
CREATE INDEX "management_reviews_tenant_id_meeting_date_index" ON "management_reviews" ("tenant_id","meeting_date");
CREATE INDEX "management_reviews_tenant_id_idx" ON "management_reviews" ("tenant_id");

CREATE TABLE "marketing_integrations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "provider" varchar(30) NOT NULL,
 "api_key" text NOT NULL,
 "sync_contacts" tinyint NOT NULL DEFAULT '1',
 "sync_events" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "marketing_integrations_tenant_id_unique" ON "marketing_integrations" ("tenant_id");

CREATE TABLE "marketplace_partners" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "name" varchar(255) NOT NULL,
 "category" varchar(50) NOT NULL,
 "description" text,
 "logo_url" varchar(255) DEFAULT NULL,
 "website_url" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);

CREATE TABLE "marketplace_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "partner_id" integer NOT NULL,
 "notes" text,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "marketplace_requests_tenant_id_index" ON "marketplace_requests" ("tenant_id");
CREATE INDEX "marketplace_requests_partner_id_index" ON "marketplace_requests" ("partner_id");
CREATE INDEX "marketplace_requests_tenant_id_idx" ON "marketplace_requests" ("tenant_id");

CREATE TABLE "material_request_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "material_request_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity_requested" numeric NOT NULL,
 "quantity_fulfilled" numeric NOT NULL DEFAULT '0.00',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "material_request_items_material_request_id_foreign" ON "material_request_items" ("material_request_id");
CREATE INDEX "material_request_items_product_id_foreign" ON "material_request_items" ("product_id");

CREATE TABLE "material_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "reference" varchar(30) NOT NULL,
 "requester_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "warehouse_id" integer DEFAULT NULL,
 "status" varchar NOT NULL DEFAULT 'pending',
 "priority" varchar NOT NULL DEFAULT 'normal',
 "justification" text,
 "rejection_reason" text,
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "material_requests_reference_unique" ON "material_requests" ("reference");
CREATE INDEX "material_requests_requester_id_foreign" ON "material_requests" ("requester_id");
CREATE INDEX "material_requests_tenant_id_index" ON "material_requests" ("tenant_id");
CREATE INDEX "material_requests_work_order_id_foreign" ON "material_requests" ("work_order_id");
CREATE INDEX "material_requests_warehouse_id_foreign" ON "material_requests" ("warehouse_id");
CREATE INDEX "material_requests_del_idx" ON "material_requests" ("deleted_at");
CREATE INDEX "material_requests_tid_st_idx" ON "material_requests" ("tenant_id","status");
CREATE INDEX "material_requests_tenant_id_idx" ON "material_requests" ("tenant_id");
CREATE INDEX "material_requests_deleted_at_idx" ON "material_requests" ("deleted_at");

CREATE TABLE "measurement_uncertainties" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_id" integer NOT NULL,
 "calibration_id" integer DEFAULT NULL,
 "measurement_type" varchar(255) NOT NULL,
 "nominal_value" numeric NOT NULL,
 "mean_value" numeric NOT NULL,
 "std_deviation" numeric NOT NULL,
 "type_a_uncertainty" numeric NOT NULL,
 "combined_uncertainty" numeric NOT NULL,
 "expanded_uncertainty" numeric NOT NULL,
 "coverage_factor" numeric NOT NULL DEFAULT '2.00',
 "unit" varchar(20) NOT NULL,
 "measured_values" text NOT NULL,
 "created_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "measurement_uncertainties_created_by_foreign" ON "measurement_uncertainties" ("created_by");
CREATE INDEX "measurement_uncertainties_equipment_id_foreign" ON "measurement_uncertainties" ("equipment_id");
CREATE INDEX "measurement_uncertainties_calibration_id_foreign" ON "measurement_uncertainties" ("calibration_id");
CREATE INDEX "measurement_uncertainties_tid_idx" ON "measurement_uncertainties" ("tenant_id");
CREATE INDEX "measurement_uncertainties_tenant_id_idx" ON "measurement_uncertainties" ("tenant_id");

CREATE TABLE "measurement_units" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "abbreviation" varchar(20) DEFAULT NULL,
 "unit_type" varchar(30) DEFAULT NULL
);
CREATE UNIQUE INDEX "measurement_units_tid_slug_uq" ON "measurement_units" ("tenant_id","slug");
CREATE INDEX "measurement_units_tid_idx" ON "measurement_units" ("tenant_id");
CREATE INDEX "measurement_units_del_idx" ON "measurement_units" ("deleted_at");
CREATE INDEX "measurement_units_deleted_at_idx" ON "measurement_units" ("deleted_at");

CREATE TABLE "migrations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "migration" varchar(255) NOT NULL,
 "batch" int NOT NULL
);

CREATE TABLE "minimum_wages" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "year" int NOT NULL,
 "month" int NOT NULL,
 "value" numeric NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "minimum_wages_year_month_unique" ON "minimum_wages" ("year","month");

CREATE TABLE "mobile_notifications" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "body" text NOT NULL,
 "type" varchar(30) DEFAULT NULL,
 "entity_type" varchar(30) DEFAULT NULL,
 "entity_id" integer DEFAULT NULL,
 "response_action" varchar(20) DEFAULT NULL,
 "responded_at" datetime NULL DEFAULT NULL,
 "read" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NOT NULL
);
CREATE INDEX "mobile_notifications_user_id_index" ON "mobile_notifications" ("user_id");
CREATE INDEX "mobile_notifications_entity_id_index" ON "mobile_notifications" ("entity_id");
CREATE INDEX "mobile_notifications_tenant_id_idx" ON "mobile_notifications" ("tenant_id");

CREATE TABLE "model_has_permissions" (
 "permission_id" integer NOT NULL,
 "model_type" varchar(255) NOT NULL,
 "model_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 PRIMARY KEY ("tenant_id","permission_id","model_id","model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" ON "model_has_permissions" ("model_id","model_type");
CREATE INDEX "model_has_permissions_permission_id_foreign" ON "model_has_permissions" ("permission_id");
CREATE INDEX "model_has_permissions_team_foreign_key_index" ON "model_has_permissions" ("tenant_id");
CREATE INDEX "model_has_permissions_tenant_id_idx" ON "model_has_permissions" ("tenant_id");

CREATE TABLE "model_has_roles" (
 "role_id" integer NOT NULL,
 "model_type" varchar(255) NOT NULL,
 "model_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 PRIMARY KEY ("tenant_id","role_id","model_id","model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" ON "model_has_roles" ("model_id","model_type");
CREATE INDEX "model_has_roles_role_id_foreign" ON "model_has_roles" ("role_id");
CREATE INDEX "model_has_roles_team_foreign_key_index" ON "model_has_roles" ("tenant_id");
CREATE INDEX "model_has_roles_tenant_id_idx" ON "model_has_roles" ("tenant_id");

CREATE TABLE "nfse_emissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "service_description" text NOT NULL,
 "amount" numeric NOT NULL,
 "iss_rate" numeric NOT NULL DEFAULT '5.00',
 "iss_amount" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "protocol_number" varchar(255) DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "nfse_emissions_tenant_id_index" ON "nfse_emissions" ("tenant_id");
CREATE INDEX "nfse_emissions_work_order_id_index" ON "nfse_emissions" ("work_order_id");
CREATE INDEX "nfse_emissions_tenant_id_idx" ON "nfse_emissions" ("tenant_id");

CREATE TABLE "non_conformances" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "number" varchar(255) NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text NOT NULL,
 "type" varchar(255) NOT NULL,
 "severity" varchar(255) NOT NULL,
 "equipment_id" integer DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "corrective_action" text,
 "root_cause" text,
 "preventive_action" text,
 "responsible_id" integer DEFAULT NULL,
 "deadline" date DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'open',
 "reported_by" integer NOT NULL,
 "closed_by" integer DEFAULT NULL,
 "closed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "non_conformances_number_unique" ON "non_conformances" ("number");
CREATE INDEX "non_conformances_responsible_id_foreign" ON "non_conformances" ("responsible_id");
CREATE INDEX "non_conformances_reported_by_foreign" ON "non_conformances" ("reported_by");
CREATE INDEX "non_conformances_closed_by_foreign" ON "non_conformances" ("closed_by");
CREATE INDEX "non_conformances_equipment_id_foreign" ON "non_conformances" ("equipment_id");
CREATE INDEX "non_conformances_work_order_id_foreign" ON "non_conformances" ("work_order_id");
CREATE INDEX "non_conformances_tid_idx" ON "non_conformances" ("tenant_id");
CREATE INDEX "non_conformances_tid_st_idx" ON "non_conformances" ("tenant_id","status");
CREATE INDEX "non_conformances_tenant_id_idx" ON "non_conformances" ("tenant_id");

CREATE TABLE "non_conformities" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "nc_number" varchar(255) NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text NOT NULL,
 "source" varchar(255) NOT NULL,
 "severity" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'open',
 "reported_by" integer NOT NULL,
 "assigned_to" integer DEFAULT NULL,
 "due_date" date DEFAULT NULL,
 "closed_at" datetime DEFAULT NULL,
 "root_cause" text,
 "corrective_action" text,
 "preventive_action" text,
 "verification_notes" text,
 "capa_record_id" integer DEFAULT NULL,
 "quality_audit_id" integer DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "non_conformities_nc_number_unique" ON "non_conformities" ("nc_number");
CREATE INDEX "non_conformities_reported_by_foreign" ON "non_conformities" ("reported_by");
CREATE INDEX "non_conformities_assigned_to_foreign" ON "non_conformities" ("assigned_to");
CREATE INDEX "non_conformities_capa_record_id_foreign" ON "non_conformities" ("capa_record_id");
CREATE INDEX "non_conformities_quality_audit_id_foreign" ON "non_conformities" ("quality_audit_id");
CREATE INDEX "non_conformities_tenant_id_idx" ON "non_conformities" ("tenant_id");
CREATE INDEX "non_conformities_deleted_at_idx" ON "non_conformities" ("deleted_at");

CREATE TABLE "notification_channels" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar(20) NOT NULL,
 "webhook_url" varchar(500) NOT NULL,
 "channel_name" varchar(100) DEFAULT NULL,
 "events" text NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "notification_channels_tenant_id_index" ON "notification_channels" ("tenant_id");
CREATE INDEX "notification_channels_tenant_id_idx" ON "notification_channels" ("tenant_id");

CREATE TABLE "notifications" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "type" varchar(50) NOT NULL,
 "title" varchar(255) NOT NULL,
 "message" text,
 "icon" varchar(30) DEFAULT NULL,
 "color" varchar(30) DEFAULT NULL,
 "link" varchar(255) DEFAULT NULL,
 "notifiable_type" varchar(255) DEFAULT NULL,
 "notifiable_id" integer DEFAULT NULL,
 "data" text DEFAULT NULL,
 "read_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "notifications_notifiable_type_notifiable_id_index" ON "notifications" ("notifiable_type","notifiable_id");
CREATE INDEX "notifications_user_id_read_at_created_at_index" ON "notifications" ("user_id","read_at","created_at");
CREATE INDEX "notifications_notif_tenant_user_read_idx" ON "notifications" ("tenant_id","notifiable_id","read_at");
CREATE INDEX "notifications_notif_user_read" ON "notifications" ("user_id","read_at");
CREATE INDEX "notifications_notif_tenant_created" ON "notifications" ("tenant_id","created_at");
CREATE INDEX "notifications_tenant_id_idx" ON "notifications" ("tenant_id");

CREATE TABLE "nps_responses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "score" tinyint NOT NULL,
 "comment" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "nps_responses_work_order_id_foreign" ON "nps_responses" ("work_order_id");
CREATE INDEX "nps_responses_customer_id_foreign" ON "nps_responses" ("customer_id");
CREATE INDEX "nps_responses_tenant_id_score_index" ON "nps_responses" ("tenant_id","score");
CREATE INDEX "nps_responses_tenant_id_idx" ON "nps_responses" ("tenant_id");

CREATE TABLE "nps_surveys" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "score" tinyint NOT NULL,
 "category" varchar(20) NOT NULL,
 "comment" text,
 "created_at" datetime NOT NULL
);
CREATE INDEX "nps_surveys_tenant_id_index" ON "nps_surveys" ("tenant_id");
CREATE INDEX "nps_surveys_customer_id_index" ON "nps_surveys" ("customer_id");
CREATE INDEX "nps_surveys_work_order_id_foreign" ON "nps_surveys" ("work_order_id");
CREATE INDEX "nps_surveys_tenant_id_idx" ON "nps_surveys" ("tenant_id");

CREATE TABLE "numbering_sequences" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "branch_id" integer DEFAULT NULL,
 "entity" varchar(50) NOT NULL,
 "prefix" varchar(10) NOT NULL DEFAULT '',
 "next_number" integer NOT NULL DEFAULT '1',
 "padding" tinyint NOT NULL DEFAULT '6',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "entity_type" varchar(50) DEFAULT NULL
);
CREATE UNIQUE INDEX "numbering_sequences_tenant_id_branch_id_entity_unique" ON "numbering_sequences" ("tenant_id","branch_id","entity");
CREATE INDEX "numbering_sequences_branch_id_foreign" ON "numbering_sequences" ("branch_id");
CREATE INDEX "numbering_sequences_numseq_tid_ent_idx" ON "numbering_sequences" ("tenant_id","entity");

CREATE TABLE "offline_map_regions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(150) NOT NULL,
 "bounds" text NOT NULL,
 "zoom_min" integer NOT NULL DEFAULT '10',
 "zoom_max" integer NOT NULL DEFAULT '16',
 "estimated_size_mb" numeric NOT NULL DEFAULT '0.00',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "description" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "offline_map_regions_tenant_id_is_active_index" ON "offline_map_regions" ("tenant_id","is_active");
CREATE INDEX "offline_map_regions_tenant_id_idx" ON "offline_map_regions" ("tenant_id");

CREATE TABLE "offline_sync_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "uuid" varchar(255) NOT NULL,
 "event_type" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL,
 "local_timestamp" datetime NOT NULL,
 "server_timestamp" datetime NOT NULL,
 "payload" text DEFAULT NULL,
 "error_message" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "offline_sync_logs_uuid_unique" ON "offline_sync_logs" ("uuid");
CREATE INDEX "offline_sync_logs_user_id_foreign" ON "offline_sync_logs" ("user_id");
CREATE INDEX "offline_sync_logs_tenant_id_user_id_index" ON "offline_sync_logs" ("tenant_id","user_id");
CREATE INDEX "offline_sync_logs_uuid_index" ON "offline_sync_logs" ("uuid");

CREATE TABLE "on_call_schedules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "date" date NOT NULL,
 "shift" varchar(255) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "on_call_schedules_tenant_id_date_shift_unique" ON "on_call_schedules" ("tenant_id","date","shift");
CREATE INDEX "on_call_schedules_user_id_foreign" ON "on_call_schedules" ("user_id");

CREATE TABLE "onboarding_checklist_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "onboarding_checklist_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "responsible_id" integer DEFAULT NULL,
 "is_completed" tinyint NOT NULL DEFAULT '0',
 "completed_at" datetime NULL DEFAULT NULL,
 "completed_by" integer DEFAULT NULL,
 "order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "onboarding_checklist_items_onboarding_checklist_id_foreign" ON "onboarding_checklist_items" ("onboarding_checklist_id");
CREATE INDEX "onboarding_checklist_items_responsible_id_foreign" ON "onboarding_checklist_items" ("responsible_id");
CREATE INDEX "onboarding_checklist_items_completed_by_foreign" ON "onboarding_checklist_items" ("completed_by");
CREATE INDEX "onboarding_checklist_items_tenant_id_index" ON "onboarding_checklist_items" ("tenant_id");
CREATE INDEX "onboarding_checklist_items_tenant_id_idx" ON "onboarding_checklist_items" ("tenant_id");

CREATE TABLE "onboarding_checklists" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "onboarding_template_id" integer DEFAULT NULL,
 "started_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'in_progress',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "onboarding_checklists_user_id_foreign" ON "onboarding_checklists" ("user_id");
CREATE INDEX "onboarding_checklists_onboarding_template_id_foreign" ON "onboarding_checklists" ("onboarding_template_id");
CREATE INDEX "onboarding_checklists_tid_idx" ON "onboarding_checklists" ("tenant_id");
CREATE INDEX "onboarding_checklists_tenant_id_idx" ON "onboarding_checklists" ("tenant_id");

CREATE TABLE "onboarding_processes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "template_id" integer NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'in_progress',
 "started_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "onboarding_processes_user_id_foreign" ON "onboarding_processes" ("user_id");
CREATE INDEX "onboarding_processes_tid_idx" ON "onboarding_processes" ("tenant_id");
CREATE INDEX "onboarding_processes_tid_st_idx" ON "onboarding_processes" ("tenant_id","status");
CREATE INDEX "onboarding_processes_template_id_index" ON "onboarding_processes" ("template_id");
CREATE INDEX "onboarding_processes_tenant_id_idx" ON "onboarding_processes" ("tenant_id");

CREATE TABLE "onboarding_steps" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "onboarding_process_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "due_date" date DEFAULT NULL,
 "position" int NOT NULL DEFAULT '0',
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "onboarding_steps_onboarding_process_id_foreign" ON "onboarding_steps" ("onboarding_process_id");

CREATE TABLE "onboarding_template_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "onboarding_template_types_tid_slug_uq" ON "onboarding_template_types" ("tenant_id","slug");
CREATE INDEX "onboarding_template_types_tid_idx" ON "onboarding_template_types" ("tenant_id");
CREATE INDEX "onboarding_template_types_del_idx" ON "onboarding_template_types" ("deleted_at");
CREATE INDEX "onboarding_template_types_deleted_at_idx" ON "onboarding_template_types" ("deleted_at");

CREATE TABLE "onboarding_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "type" varchar(30) NOT NULL DEFAULT 'admission',
 "default_tasks" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "onboarding_templates_tid_idx" ON "onboarding_templates" ("tenant_id");
CREATE INDEX "onboarding_templates_tenant_id_idx" ON "onboarding_templates" ("tenant_id");

CREATE TABLE "online_payments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "receivable_id" integer NOT NULL,
 "method" varchar(20) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'processing',
 "gateway_id" varchar(255) DEFAULT NULL,
 "amount" numeric DEFAULT NULL,
 "paid_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "online_payments_tenant_id_index" ON "online_payments" ("tenant_id");
CREATE INDEX "online_payments_receivable_id_index" ON "online_payments" ("receivable_id");
CREATE INDEX "online_payments_gateway_id_index" ON "online_payments" ("gateway_id");
CREATE INDEX "online_payments_tenant_id_idx" ON "online_payments" ("tenant_id");

CREATE TABLE "operational_snapshots" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "status" varchar(32) NOT NULL,
 "alerts_count" int NOT NULL DEFAULT '0',
 "health_payload" text NOT NULL,
 "metrics_payload" text DEFAULT NULL,
 "alerts_payload" text DEFAULT NULL,
 "captured_at" datetime NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "operational_snapshots_status_index" ON "operational_snapshots" ("status");
CREATE INDEX "operational_snapshots_captured_at_index" ON "operational_snapshots" ("captured_at");
CREATE INDEX "operational_snapshots_tenant_id_idx" ON "operational_snapshots" ("tenant_id");

CREATE TABLE "overnight_stays" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "travel_request_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "stay_date" date NOT NULL,
 "hotel_name" varchar(255) DEFAULT NULL,
 "city" varchar(255) NOT NULL,
 "state" varchar(255) DEFAULT NULL,
 "cost" numeric DEFAULT NULL,
 "receipt_path" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "overnight_stays_travel_request_id_foreign" ON "overnight_stays" ("travel_request_id");
CREATE INDEX "overnight_stays_user_id_foreign" ON "overnight_stays" ("user_id");
CREATE INDEX "overnight_stays_tenant_id_travel_request_id_index" ON "overnight_stays" ("tenant_id","travel_request_id");

CREATE TABLE "partial_payments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "account_receivable_id" integer NOT NULL,
 "amount" numeric NOT NULL,
 "payment_date" date NOT NULL,
 "payment_method" varchar(255) DEFAULT NULL,
 "notes" varchar(500) DEFAULT NULL,
 "created_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "partial_payments_created_by_foreign" ON "partial_payments" ("created_by");
CREATE INDEX "partial_payments_tid_idx" ON "partial_payments" ("tenant_id");
CREATE INDEX "partial_payments_account_receivable_i_fk_idx" ON "partial_payments" ("account_receivable_id");
CREATE INDEX "partial_payments_tenant_id_idx" ON "partial_payments" ("tenant_id");

CREATE TABLE "parts_kit_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "parts_kit_id" integer NOT NULL,
 "type" varchar(255) NOT NULL DEFAULT 'product',
 "reference_id" integer DEFAULT NULL,
 "description" varchar(255) NOT NULL,
 "quantity" numeric NOT NULL DEFAULT '1.00',
 "unit_price" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "parts_kit_items_parts_kit_id_index" ON "parts_kit_items" ("parts_kit_id");
CREATE INDEX "parts_kit_items_reference_id_index" ON "parts_kit_items" ("reference_id");

CREATE TABLE "parts_kits" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "parts_kits_tenant_id_index" ON "parts_kits" ("tenant_id");
CREATE INDEX "parts_kits_del_idx" ON "parts_kits" ("deleted_at");
CREATE INDEX "parts_kits_tenant_id_idx" ON "parts_kits" ("tenant_id");
CREATE INDEX "parts_kits_deleted_at_idx" ON "parts_kits" ("deleted_at");

CREATE TABLE "password_policies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "min_length" int NOT NULL DEFAULT '8',
 "require_uppercase" tinyint NOT NULL DEFAULT '1',
 "require_lowercase" tinyint NOT NULL DEFAULT '1',
 "require_number" tinyint NOT NULL DEFAULT '1',
 "require_special" tinyint NOT NULL DEFAULT '0',
 "expiry_days" int NOT NULL DEFAULT '90',
 "max_attempts" int NOT NULL DEFAULT '5',
 "lockout_minutes" int NOT NULL DEFAULT '15',
 "history_count" int NOT NULL DEFAULT '3',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "password_policies_tenant_id_unique" ON "password_policies" ("tenant_id");

CREATE TABLE "password_reset_tokens" (
 "email" varchar(255) NOT NULL,
 "token" varchar(255) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 PRIMARY KEY ("email")
);

CREATE TABLE "payment_gateway_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "gateway" varchar(30) NOT NULL DEFAULT 'none',
 "api_key" text,
 "api_secret" text,
 "methods" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "payment_gateway_configs_tenant_id_unique" ON "payment_gateway_configs" ("tenant_id");

CREATE TABLE "payment_methods" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "code" varchar(30) NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "payment_methods_tenant_id_code_unique" ON "payment_methods" ("tenant_id","code");

CREATE TABLE "payment_receipts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "payment_id" integer NOT NULL,
 "receipt_number" varchar(255) NOT NULL,
 "pdf_path" varchar(255) DEFAULT NULL,
 "generated_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "payment_receipts_tenant_id_receipt_number_unique" ON "payment_receipts" ("tenant_id","receipt_number");
CREATE INDEX "payment_receipts_payment_id_foreign" ON "payment_receipts" ("payment_id");
CREATE INDEX "payment_receipts_generated_by_foreign" ON "payment_receipts" ("generated_by");

CREATE TABLE "payment_terms" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "payment_terms_tid_slug_uq" ON "payment_terms" ("tenant_id","slug");
CREATE INDEX "payment_terms_tid_idx" ON "payment_terms" ("tenant_id");
CREATE INDEX "payment_terms_del_idx" ON "payment_terms" ("deleted_at");
CREATE INDEX "payment_terms_deleted_at_idx" ON "payment_terms" ("deleted_at");

CREATE TABLE "payments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "payable_type" varchar(255) NOT NULL,
 "payable_id" integer NOT NULL,
 "received_by" integer DEFAULT NULL,
 "amount" numeric NOT NULL,
 "payment_method" varchar(30) NOT NULL,
 "payment_date" date NOT NULL,
 "notes" text,
 "external_id" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "paid_at" datetime NULL DEFAULT NULL,
 "gateway_response" text DEFAULT NULL,
 "gateway_provider" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "payments_payable_type_payable_id_index" ON "payments" ("payable_type","payable_id");
CREATE INDEX "payments_pay_tenant_payable" ON "payments" ("tenant_id","payable_type","payable_id");
CREATE INDEX "payments_pay_tenant_date" ON "payments" ("tenant_id","payment_date");
CREATE INDEX "payments_received_by_foreign" ON "payments" ("received_by");
CREATE INDEX "payments_tid_idx" ON "payments" ("tenant_id");
CREATE INDEX "payments_pay_tid_pdate_idx" ON "payments" ("tenant_id","payment_date");
CREATE INDEX "payments_idx_payments_external_id" ON "payments" ("external_id");
CREATE INDEX "payments_tenant_id_idx" ON "payments" ("tenant_id");

CREATE TABLE "payroll_lines" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "payroll_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "gross_salary" numeric NOT NULL DEFAULT '0.00',
 "net_salary" numeric NOT NULL DEFAULT '0.00',
 "base_salary" numeric NOT NULL DEFAULT '0.00',
 "overtime_50_hours" numeric NOT NULL DEFAULT '0.00',
 "overtime_50_value" numeric NOT NULL DEFAULT '0.00',
 "overtime_100_hours" numeric NOT NULL DEFAULT '0.00',
 "overtime_100_value" numeric NOT NULL DEFAULT '0.00',
 "night_hours" numeric NOT NULL DEFAULT '0.00',
 "night_shift_value" numeric NOT NULL DEFAULT '0.00',
 "dsr_value" numeric NOT NULL DEFAULT '0.00',
 "commission_value" numeric NOT NULL DEFAULT '0.00',
 "bonus_value" numeric NOT NULL DEFAULT '0.00',
 "other_earnings" numeric NOT NULL DEFAULT '0.00',
 "inss_employee" numeric NOT NULL DEFAULT '0.00',
 "irrf" numeric NOT NULL DEFAULT '0.00',
 "transportation_discount" numeric NOT NULL DEFAULT '0.00',
 "meal_discount" numeric NOT NULL DEFAULT '0.00',
 "health_insurance_discount" numeric NOT NULL DEFAULT '0.00',
 "other_deductions" numeric NOT NULL DEFAULT '0.00',
 "advance_discount" numeric NOT NULL DEFAULT '0.00',
 "advance_deduction" numeric NOT NULL DEFAULT '0.00',
 "fgts_value" numeric NOT NULL DEFAULT '0.00',
 "inss_employer_value" numeric NOT NULL DEFAULT '0.00',
 "worked_days" int NOT NULL DEFAULT '0',
 "absence_days" int NOT NULL DEFAULT '0',
 "absence_value" numeric NOT NULL DEFAULT '0.00',
 "hour_bank_payout_hours" numeric NOT NULL DEFAULT '0.00',
 "hour_bank_payout_value" numeric NOT NULL DEFAULT '0.00',
 "vt_deduction" numeric NOT NULL DEFAULT '0.00',
 "vr_deduction" numeric NOT NULL DEFAULT '0.00',
 "vacation_days" int NOT NULL DEFAULT '0',
 "vacation_value" numeric NOT NULL DEFAULT '0.00',
 "vacation_bonus" numeric NOT NULL DEFAULT '0.00',
 "thirteenth_value" numeric NOT NULL DEFAULT '0.00',
 "thirteenth_months" int NOT NULL DEFAULT '0',
 "status" varchar(20) NOT NULL DEFAULT 'calculated',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "payroll_lines_user_id_foreign" ON "payroll_lines" ("user_id");
CREATE INDEX "payroll_lines_payroll_id_user_id_index" ON "payroll_lines" ("payroll_id","user_id");
CREATE INDEX "payroll_lines_tenant_id_idx" ON "payroll_lines" ("tenant_id");

CREATE TABLE "payrolls" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "reference_month" varchar(7) NOT NULL,
 "type" varchar(30) NOT NULL DEFAULT 'regular',
 "status" varchar(20) NOT NULL DEFAULT 'draft',
 "total_gross" numeric NOT NULL DEFAULT '0.00',
 "total_deductions" numeric NOT NULL DEFAULT '0.00',
 "total_net" numeric NOT NULL DEFAULT '0.00',
 "total_fgts" numeric NOT NULL DEFAULT '0.00',
 "total_inss_employer" numeric NOT NULL DEFAULT '0.00',
 "employee_count" int NOT NULL DEFAULT '0',
 "calculated_by" integer DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "calculated_at" datetime NULL DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "paid_at" datetime NULL DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "payrolls_tenant_id_reference_month_type_unique" ON "payrolls" ("tenant_id","reference_month","type");
CREATE INDEX "payrolls_calculated_by_foreign" ON "payrolls" ("calculated_by");
CREATE INDEX "payrolls_approved_by_foreign" ON "payrolls" ("approved_by");

CREATE TABLE "payslips" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "payroll_line_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "reference_month" varchar(7) NOT NULL,
 "file_path" varchar(255) DEFAULT NULL,
 "sent_at" datetime NULL DEFAULT NULL,
 "viewed_at" datetime NULL DEFAULT NULL,
 "digital_signature_hash" varchar(64) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "payslips_payroll_line_id_foreign" ON "payslips" ("payroll_line_id");
CREATE INDEX "payslips_user_id_foreign" ON "payslips" ("user_id");
CREATE INDEX "payslips_tenant_id_user_id_reference_month_index" ON "payslips" ("tenant_id","user_id","reference_month");
CREATE INDEX "payslips_tenant_id_idx" ON "payslips" ("tenant_id");

CREATE TABLE "performance_reviews" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "reviewer_id" integer NOT NULL,
 "cycle" varchar(255) DEFAULT NULL,
 "year" year NOT NULL,
 "type" varchar NOT NULL DEFAULT 'manager',
 "status" varchar NOT NULL DEFAULT 'draft',
 "ratings" text DEFAULT NULL,
 "okrs" text DEFAULT NULL,
 "nine_box_potential" int DEFAULT NULL,
 "nine_box_performance" int DEFAULT NULL,
 "action_plan" text,
 "comments" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "title" varchar(255) DEFAULT NULL
);
CREATE INDEX "performance_reviews_tid_idx" ON "performance_reviews" ("tenant_id");
CREATE INDEX "performance_reviews_user_id_fk_idx" ON "performance_reviews" ("user_id");
CREATE INDEX "performance_reviews_reviewer_id_fk_idx" ON "performance_reviews" ("reviewer_id");
CREATE INDEX "performance_reviews_tid_st_idx" ON "performance_reviews" ("tenant_id","status");
CREATE INDEX "performance_reviews_tenant_id_idx" ON "performance_reviews" ("tenant_id");

CREATE TABLE "permission_groups" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) DEFAULT NULL,
 "order" integer NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "permission_groups_slug_index" ON "permission_groups" ("slug");

CREATE TABLE "permissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "name" varchar(255) NOT NULL,
 "guard_name" varchar(255) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "group_id" integer DEFAULT NULL,
 "criticality" varchar NOT NULL DEFAULT 'MED'
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" ON "permissions" ("name","guard_name");
CREATE INDEX "permissions_group_id_foreign" ON "permissions" ("group_id");

CREATE TABLE "personal_access_tokens" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tokenable_type" varchar(255) NOT NULL,
 "tokenable_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "token" varchar(64) NOT NULL,
 "abilities" text,
 "expires_at" datetime NULL DEFAULT NULL,
 "last_used_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" ON "personal_access_tokens" ("token");
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" ON "personal_access_tokens" ("tokenable_type","tokenable_id");

CREATE TABLE "photo_annotations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "image_path" varchar(255) NOT NULL,
 "annotations" text NOT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "photo_annotations_tenant_id_index" ON "photo_annotations" ("tenant_id");
CREATE INDEX "photo_annotations_work_order_id_index" ON "photo_annotations" ("work_order_id");
CREATE INDEX "photo_annotations_user_id_foreign" ON "photo_annotations" ("user_id");
CREATE INDEX "photo_annotations_tenant_id_idx" ON "photo_annotations" ("tenant_id");

CREATE TABLE "portal_guest_links" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "token" varchar(64) NOT NULL,
 "entity_type" varchar(255) NOT NULL,
 "entity_id" integer NOT NULL,
 "expires_at" datetime NOT NULL,
 "used_at" datetime NULL DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "single_use" tinyint NOT NULL DEFAULT '1',
 "consumed_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "portal_guest_links_token_unique" ON "portal_guest_links" ("token");
CREATE INDEX "portal_guest_links_entity_type_entity_id_index" ON "portal_guest_links" ("entity_type","entity_id");
CREATE INDEX "portal_guest_links_created_by_foreign" ON "portal_guest_links" ("created_by");
CREATE INDEX "portal_guest_links_tenant_id_entity_type_entity_id_index" ON "portal_guest_links" ("tenant_id","entity_type","entity_id");
CREATE INDEX "portal_guest_links_tenant_id_idx" ON "portal_guest_links" ("tenant_id");

CREATE TABLE "portal_ticket_comments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "portal_ticket_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "content" text NOT NULL,
 "is_internal" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "portal_ticket_comments_ptc_tenant_ticket_idx" ON "portal_ticket_comments" ("tenant_id","portal_ticket_id");
CREATE INDEX "portal_ticket_comments_tenant_id_idx" ON "portal_ticket_comments" ("tenant_id");

CREATE TABLE "portal_ticket_messages" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "portal_ticket_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "message" text NOT NULL,
 "is_internal" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "portal_ticket_messages_portal_ticket_id_index" ON "portal_ticket_messages" ("portal_ticket_id");
CREATE INDEX "portal_ticket_messages_user_id_index" ON "portal_ticket_messages" ("user_id");

CREATE TABLE "portal_tickets" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "created_by" integer DEFAULT NULL,
 "equipment_id" integer DEFAULT NULL,
 "ticket_number" varchar(255) DEFAULT NULL,
 "subject" varchar(255) NOT NULL,
 "description" text,
 "priority" varchar(255) NOT NULL DEFAULT 'normal',
 "status" varchar(255) NOT NULL DEFAULT 'open',
 "category" varchar(255) DEFAULT NULL,
 "sla_due_at" datetime NULL DEFAULT NULL,
 "paused_at" datetime NULL DEFAULT NULL,
 "source" varchar(255) DEFAULT NULL,
 "assigned_to" integer DEFAULT NULL,
 "resolved_at" datetime NULL DEFAULT NULL,
 "qr_code" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "portal_tickets_tenant_id_index" ON "portal_tickets" ("tenant_id");
CREATE INDEX "portal_tickets_customer_id_index" ON "portal_tickets" ("customer_id");
CREATE INDEX "portal_tickets_created_by_index" ON "portal_tickets" ("created_by");
CREATE INDEX "portal_tickets_equipment_id_index" ON "portal_tickets" ("equipment_id");
CREATE INDEX "portal_tickets_assigned_to_index" ON "portal_tickets" ("assigned_to");
CREATE INDEX "portal_tickets_tenant_id_idx" ON "portal_tickets" ("tenant_id");

CREATE TABLE "portal_white_label" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "company_name" varchar(255) DEFAULT NULL,
 "logo_url" varchar(255) DEFAULT NULL,
 "primary_color" varchar(7) NOT NULL DEFAULT '#3B82F6',
 "secondary_color" varchar(7) NOT NULL DEFAULT '#10B981',
 "custom_css" text,
 "custom_domain" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "portal_white_label_tenant_id_unique" ON "portal_white_label" ("tenant_id");

CREATE TABLE "positions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "department_id" integer NOT NULL,
 "level" varchar NOT NULL DEFAULT 'pleno',
 "description" text,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "positions_department_id_foreign" ON "positions" ("department_id");
CREATE INDEX "positions_tid_idx" ON "positions" ("tenant_id");
CREATE INDEX "positions_tenant_id_idx" ON "positions" ("tenant_id");

CREATE TABLE "price_histories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "priceable_type" varchar(255) NOT NULL,
 "priceable_id" integer NOT NULL,
 "old_cost_price" numeric DEFAULT NULL,
 "new_cost_price" numeric DEFAULT NULL,
 "old_sell_price" numeric DEFAULT NULL,
 "new_sell_price" numeric DEFAULT NULL,
 "change_percent" numeric DEFAULT NULL,
 "reason" varchar(255) DEFAULT NULL,
 "changed_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "price_histories_priceable_type_priceable_id_index" ON "price_histories" ("priceable_type","priceable_id");
CREATE INDEX "price_histories_changed_by_foreign" ON "price_histories" ("changed_by");
CREATE INDEX "price_histories_idx_price_hist_type_id_created" ON "price_histories" ("priceable_type","priceable_id","created_at");
CREATE INDEX "price_histories_tid_idx" ON "price_histories" ("tenant_id");
CREATE INDEX "price_histories_tenant_id_idx" ON "price_histories" ("tenant_id");

CREATE TABLE "price_table_adjustment_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "price_table_adjustment_types_tid_slug_uq" ON "price_table_adjustment_types" ("tenant_id","slug");
CREATE INDEX "price_table_adjustment_types_tid_idx" ON "price_table_adjustment_types" ("tenant_id");
CREATE INDEX "price_table_adjustment_types_del_idx" ON "price_table_adjustment_types" ("deleted_at");
CREATE INDEX "price_table_adjustment_types_deleted_at_idx" ON "price_table_adjustment_types" ("deleted_at");

CREATE TABLE "price_table_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "price_table_id" integer NOT NULL,
 "priceable_type" varchar(255) NOT NULL,
 "priceable_id" integer NOT NULL,
 "price" numeric NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "price_table_items_price_table_id_foreign" ON "price_table_items" ("price_table_id");
CREATE INDEX "price_table_items_priceable_type_priceable_id_index" ON "price_table_items" ("priceable_type","priceable_id");

CREATE TABLE "price_tables" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "region" varchar(100) DEFAULT NULL,
 "customer_type" varchar(50) DEFAULT NULL,
 "multiplier" numeric NOT NULL DEFAULT '1.0000',
 "is_default" tinyint NOT NULL DEFAULT '0',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "valid_from" date DEFAULT NULL,
 "valid_until" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "price_tables_tid_idx" ON "price_tables" ("tenant_id");
CREATE INDEX "price_tables_del_idx" ON "price_tables" ("deleted_at");
CREATE INDEX "price_tables_tenant_id_idx" ON "price_tables" ("tenant_id");
CREATE INDEX "price_tables_deleted_at_idx" ON "price_tables" ("deleted_at");

CREATE TABLE "print_jobs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "document_type" varchar(20) NOT NULL,
 "document_id" integer NOT NULL,
 "printer_type" varchar(20) NOT NULL,
 "copies" int NOT NULL DEFAULT '1',
 "status" varchar(20) NOT NULL DEFAULT 'queued',
 "printed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "print_jobs_tenant_id_index" ON "print_jobs" ("tenant_id");
CREATE INDEX "print_jobs_user_id_index" ON "print_jobs" ("user_id");
CREATE INDEX "print_jobs_document_id_index" ON "print_jobs" ("document_id");
CREATE INDEX "print_jobs_tenant_id_idx" ON "print_jobs" ("tenant_id");

CREATE TABLE "privacy_consents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "consent_type" varchar(30) NOT NULL,
 "granted" tinyint NOT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "user_agent" text,
 "consented_at" datetime NOT NULL
);
CREATE INDEX "privacy_consents_tenant_id_index" ON "privacy_consents" ("tenant_id");
CREATE INDEX "privacy_consents_user_id_index" ON "privacy_consents" ("user_id");
CREATE INDEX "privacy_consents_tenant_id_idx" ON "privacy_consents" ("tenant_id");

CREATE TABLE "product_categories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "product_categories_tenant_id_name_unique" ON "product_categories" ("tenant_id","name");
CREATE INDEX "product_categories_pcat_tid_act_idx" ON "product_categories" ("tenant_id","is_active");

CREATE TABLE "product_kits" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "parent_id" integer NOT NULL,
 "child_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "product_kits_parent_id_foreign" ON "product_kits" ("parent_id");
CREATE INDEX "product_kits_child_id_foreign" ON "product_kits" ("child_id");

CREATE TABLE "product_serials" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "warehouse_id" integer DEFAULT NULL,
 "serial_number" varchar(255) NOT NULL,
 "status" varchar NOT NULL DEFAULT 'available',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "ps_unique" ON "product_serials" ("tenant_id","serial_number");
CREATE INDEX "product_serials_del_idx" ON "product_serials" ("deleted_at");
CREATE INDEX "product_serials_product_id_fk_idx" ON "product_serials" ("product_id");
CREATE INDEX "product_serials_warehouse_id_fk_idx" ON "product_serials" ("warehouse_id");
CREATE INDEX "product_serials_deleted_at_idx" ON "product_serials" ("deleted_at");

CREATE TABLE "products" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "category_id" integer DEFAULT NULL,
 "code" varchar(50) DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "unit" varchar(10) NOT NULL DEFAULT 'UN',
 "cost_price" numeric NOT NULL DEFAULT '0.00',
 "sell_price" numeric NOT NULL DEFAULT '0.00',
 "stock_qty" numeric NOT NULL DEFAULT '0.00',
 "stock_min" numeric NOT NULL DEFAULT '0.00',
 "track_stock" tinyint NOT NULL DEFAULT '1',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "is_kit" tinyint NOT NULL DEFAULT '0',
 "track_batch" tinyint NOT NULL DEFAULT '0',
 "track_serial" tinyint NOT NULL DEFAULT '0',
 "min_repo_point" numeric DEFAULT NULL,
 "manufacturer_code" varchar(100) DEFAULT NULL,
 "storage_location" varchar(100) DEFAULT NULL,
 "qr_hash" varchar(255) DEFAULT NULL,
 "max_stock" numeric DEFAULT NULL,
 "default_supplier_id" integer DEFAULT NULL,
 "sku" varchar(100) DEFAULT NULL,
 "price" numeric DEFAULT NULL,
 "cost" numeric DEFAULT NULL,
 "type" varchar(30) DEFAULT NULL,
 "min_stock" numeric DEFAULT NULL,
 "ncm" varchar(10) DEFAULT NULL,
 "image_url" varchar(500) DEFAULT NULL,
 "barcode" varchar(50) DEFAULT NULL,
 "brand" varchar(100) DEFAULT NULL,
 "weight" numeric DEFAULT NULL,
 "width" numeric DEFAULT NULL,
 "height" numeric DEFAULT NULL,
 "depth" numeric DEFAULT NULL
);
CREATE UNIQUE INDEX "products_tenant_id_code_unique" ON "products" ("tenant_id","code");
CREATE UNIQUE INDEX "products_qr_hash_unique" ON "products" ("qr_hash");
CREATE UNIQUE INDEX "products_sku_unique" ON "products" ("sku");
CREATE INDEX "products_tenant_id_name_index" ON "products" ("tenant_id","name");
CREATE INDEX "products_default_supplier_id_foreign" ON "products" ("default_supplier_id");
CREATE INDEX "products_prod_tenant_category" ON "products" ("tenant_id","category_id");
CREATE INDEX "products_prod_deleted_at" ON "products" ("tenant_id","deleted_at");
CREATE INDEX "products_del_idx" ON "products" ("deleted_at");
CREATE INDEX "products_category_id_fk_idx" ON "products" ("category_id");
CREATE INDEX "products_deleted_at_idx" ON "products" ("deleted_at");

CREATE TABLE "project_milestones" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "project_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "order" int NOT NULL,
 "planned_start" date DEFAULT NULL,
 "planned_end" date DEFAULT NULL,
 "actual_start" date DEFAULT NULL,
 "actual_end" date DEFAULT NULL,
 "billing_value" numeric DEFAULT NULL,
 "billing_percent" numeric DEFAULT NULL,
 "invoice_id" integer DEFAULT NULL,
 "weight" numeric NOT NULL DEFAULT '1.00',
 "dependencies" text DEFAULT NULL,
 "deliverables" text,
 "completed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "project_milestones_project_id_foreign" ON "project_milestones" ("project_id");
CREATE INDEX "project_milestones_invoice_id_foreign" ON "project_milestones" ("invoice_id");
CREATE INDEX "project_milestones_tenant_project_idx" ON "project_milestones" ("tenant_id","project_id");
CREATE INDEX "project_milestones_tenant_id_idx" ON "project_milestones" ("tenant_id");

CREATE TABLE "project_resources" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "project_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "role" varchar(100) NOT NULL,
 "allocation_percent" numeric NOT NULL,
 "start_date" date NOT NULL,
 "end_date" date NOT NULL,
 "hourly_rate" numeric DEFAULT NULL,
 "total_hours_planned" numeric DEFAULT NULL,
 "total_hours_logged" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "project_resources_project_id_foreign" ON "project_resources" ("project_id");
CREATE INDEX "project_resources_user_id_foreign" ON "project_resources" ("user_id");
CREATE INDEX "project_resources_tenant_project_idx" ON "project_resources" ("tenant_id","project_id");
CREATE INDEX "project_resources_tenant_id_idx" ON "project_resources" ("tenant_id");

CREATE TABLE "project_time_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "project_id" integer NOT NULL,
 "project_resource_id" integer NOT NULL,
 "milestone_id" integer DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "date" date NOT NULL,
 "hours" numeric NOT NULL,
 "description" text,
 "billable" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "project_time_entries_project_id_foreign" ON "project_time_entries" ("project_id");
CREATE INDEX "project_time_entries_project_resource_id_foreign" ON "project_time_entries" ("project_resource_id");
CREATE INDEX "project_time_entries_milestone_id_foreign" ON "project_time_entries" ("milestone_id");
CREATE INDEX "project_time_entries_work_order_id_foreign" ON "project_time_entries" ("work_order_id");
CREATE INDEX "project_time_entries_tenant_project_idx" ON "project_time_entries" ("tenant_id","project_id");
CREATE INDEX "project_time_entries_tenant_id_idx" ON "project_time_entries" ("tenant_id");

CREATE TABLE "projects" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "crm_deal_id" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "code" varchar(50) NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "status" varchar(255) NOT NULL DEFAULT 'planning',
 "priority" varchar(255) NOT NULL DEFAULT 'medium',
 "start_date" date DEFAULT NULL,
 "end_date" date DEFAULT NULL,
 "actual_start_date" date DEFAULT NULL,
 "actual_end_date" date DEFAULT NULL,
 "budget" numeric NOT NULL DEFAULT '0.00',
 "spent" numeric NOT NULL DEFAULT '0.00',
 "progress_percent" numeric NOT NULL DEFAULT '0.00',
 "billing_type" varchar(50) NOT NULL DEFAULT 'fixed_price',
 "hourly_rate" numeric DEFAULT NULL,
 "tags" text DEFAULT NULL,
 "manager_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "prj_tenant_code_uq" ON "projects" ("tenant_id","code");
CREATE INDEX "projects_customer_id_foreign" ON "projects" ("customer_id");
CREATE INDEX "projects_crm_deal_id_foreign" ON "projects" ("crm_deal_id");
CREATE INDEX "projects_created_by_foreign" ON "projects" ("created_by");
CREATE INDEX "projects_manager_id_foreign" ON "projects" ("manager_id");
CREATE INDEX "projects_deleted_at_idx" ON "projects" ("deleted_at");

CREATE TABLE "psei_submissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "seal_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "equipment_id" integer DEFAULT NULL,
 "submission_type" varchar(20) NOT NULL DEFAULT 'automatic',
 "status" varchar(20) NOT NULL DEFAULT 'queued',
 "attempt_number" integer NOT NULL DEFAULT '1',
 "max_attempts" integer NOT NULL DEFAULT '3',
 "protocol_number" varchar(100) DEFAULT NULL,
 "request_payload" text DEFAULT NULL,
 "response_payload" text DEFAULT NULL,
 "error_message" text,
 "submitted_at" datetime NULL DEFAULT NULL,
 "confirmed_at" datetime NULL DEFAULT NULL,
 "next_retry_at" datetime NULL DEFAULT NULL,
 "submitted_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "psei_submissions_seal_id_foreign" ON "psei_submissions" ("seal_id");
CREATE INDEX "psei_submissions_work_order_id_foreign" ON "psei_submissions" ("work_order_id");
CREATE INDEX "psei_submissions_equipment_id_foreign" ON "psei_submissions" ("equipment_id");
CREATE INDEX "psei_submissions_submitted_by_foreign" ON "psei_submissions" ("submitted_by");
CREATE INDEX "psei_submissions_tenant_id_status_index" ON "psei_submissions" ("tenant_id","status");
CREATE INDEX "psei_submissions_tenant_id_seal_id_index" ON "psei_submissions" ("tenant_id","seal_id");
CREATE INDEX "psei_submissions_idx_psei_retry" ON "psei_submissions" ("status","next_retry_at");
CREATE INDEX "psei_submissions_tenant_id_idx" ON "psei_submissions" ("tenant_id");

CREATE TABLE "purchase_quotation_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "purchase_quotation_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "unit_price" numeric NOT NULL,
 "total" numeric NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE INDEX "purchase_quotation_items_purchase_quotation_id_foreign" ON "purchase_quotation_items" ("purchase_quotation_id");
CREATE INDEX "purchase_quotation_items_product_id_foreign" ON "purchase_quotation_items" ("product_id");
CREATE INDEX "purchase_quotation_items_pqi_tenant_quotation_idx" ON "purchase_quotation_items" ("tenant_id","purchase_quotation_id");
CREATE INDEX "purchase_quotation_items_tenant_id_idx" ON "purchase_quotation_items" ("tenant_id");

CREATE TABLE "purchase_quotations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "supplier_id" integer NOT NULL,
 "total" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "notes" text,
 "valid_until" date DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "reference" varchar(255) DEFAULT NULL,
 "total_amount" numeric NOT NULL DEFAULT '0.00',
 "requested_by" integer DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "purchase_quotations_supplier_id_foreign" ON "purchase_quotations" ("supplier_id");
CREATE INDEX "purchase_quotations_requested_by_foreign" ON "purchase_quotations" ("requested_by");
CREATE INDEX "purchase_quotations_approved_by_foreign" ON "purchase_quotations" ("approved_by");
CREATE INDEX "purchase_quotations_tid_idx" ON "purchase_quotations" ("tenant_id");
CREATE INDEX "purchase_quotations_del_idx" ON "purchase_quotations" ("deleted_at");
CREATE INDEX "purchase_quotations_tid_st_idx" ON "purchase_quotations" ("tenant_id","status");
CREATE INDEX "purchase_quotations_tenant_id_idx" ON "purchase_quotations" ("tenant_id");
CREATE INDEX "purchase_quotations_deleted_at_idx" ON "purchase_quotations" ("deleted_at");

CREATE TABLE "purchase_quote_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "purchase_quote_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "unit" varchar(20) DEFAULT NULL,
 "specifications" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "purchase_quote_items_purchase_quote_id_foreign" ON "purchase_quote_items" ("purchase_quote_id");
CREATE INDEX "purchase_quote_items_product_id_foreign" ON "purchase_quote_items" ("product_id");

CREATE TABLE "purchase_quote_suppliers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "purchase_quote_id" integer NOT NULL,
 "supplier_id" integer NOT NULL,
 "status" varchar NOT NULL DEFAULT 'pending',
 "total_price" numeric DEFAULT NULL,
 "delivery_days" int DEFAULT NULL,
 "conditions" text,
 "item_prices" text DEFAULT NULL,
 "responded_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "purchase_quote_suppliers_purchase_quote_id_foreign" ON "purchase_quote_suppliers" ("purchase_quote_id");
CREATE INDEX "purchase_quote_suppliers_supplier_id_foreign" ON "purchase_quote_suppliers" ("supplier_id");

CREATE TABLE "purchase_quotes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "reference" varchar(30) NOT NULL,
 "title" varchar(255) NOT NULL,
 "notes" text,
 "status" varchar NOT NULL DEFAULT 'draft',
 "deadline" date DEFAULT NULL,
 "approved_supplier_id" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "purchase_quotes_reference_unique" ON "purchase_quotes" ("reference");
CREATE INDEX "purchase_quotes_tenant_id_index" ON "purchase_quotes" ("tenant_id");
CREATE INDEX "purchase_quotes_approved_supplier_id_foreign" ON "purchase_quotes" ("approved_supplier_id");
CREATE INDEX "purchase_quotes_del_idx" ON "purchase_quotes" ("deleted_at");
CREATE INDEX "purchase_quotes_tid_st_idx" ON "purchase_quotes" ("tenant_id","status");
CREATE INDEX "purchase_quotes_tenant_id_idx" ON "purchase_quotes" ("tenant_id");
CREATE INDEX "purchase_quotes_deleted_at_idx" ON "purchase_quotes" ("deleted_at");

CREATE TABLE "push_subscriptions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "user_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "endpoint" varchar(760) NOT NULL,
 "p256dh_key" varchar(500) NOT NULL,
 "auth_key" varchar(500) NOT NULL,
 "user_agent" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "push_sub_user_endpoint_unique" ON "push_subscriptions" ("user_id","endpoint");
CREATE INDEX "push_subscriptions_user_id_index" ON "push_subscriptions" ("user_id");
CREATE INDEX "push_subscriptions_tenant_id_index" ON "push_subscriptions" ("tenant_id");
CREATE INDEX "push_subscriptions_ps_user" ON "push_subscriptions" ("user_id");
CREATE INDEX "push_subscriptions_tenant_id_idx" ON "push_subscriptions" ("tenant_id");

CREATE TABLE "qa_alerts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "calibration_id" integer NOT NULL,
 "similarity_score" numeric NOT NULL DEFAULT '0.00',
 "reason" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "reviewed_by" integer DEFAULT NULL,
 "reviewed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "qa_alerts_calibration_id_foreign" ON "qa_alerts" ("calibration_id");
CREATE INDEX "qa_alerts_reviewed_by_foreign" ON "qa_alerts" ("reviewed_by");
CREATE INDEX "qa_alerts_tid_idx" ON "qa_alerts" ("tenant_id");
CREATE INDEX "qa_alerts_tid_st_idx" ON "qa_alerts" ("tenant_id","status");
CREATE INDEX "qa_alerts_tenant_id_idx" ON "qa_alerts" ("tenant_id");

CREATE TABLE "qr_scans" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "work_order_id" integer NOT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "user_agent" varchar(500) DEFAULT NULL,
 "scanned_at" datetime NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "qr_scans_work_order_id_index" ON "qr_scans" ("work_order_id");
CREATE INDEX "qr_scans_tenant_id_idx" ON "qr_scans" ("tenant_id");

CREATE TABLE "quality_audit_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "quality_audit_id" integer NOT NULL,
 "requirement" varchar(255) NOT NULL,
 "clause" varchar(255) DEFAULT NULL,
 "question" text NOT NULL,
 "result" varchar(255) DEFAULT NULL,
 "evidence" text,
 "notes" text,
 "item_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "quality_audit_items_quality_audit_id_foreign" ON "quality_audit_items" ("quality_audit_id");

CREATE TABLE "quality_audits" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "audit_number" varchar(255) NOT NULL,
 "title" varchar(255) NOT NULL,
 "type" varchar(255) NOT NULL DEFAULT 'internal',
 "scope" varchar(255) DEFAULT NULL,
 "planned_date" date NOT NULL,
 "executed_date" date DEFAULT NULL,
 "auditor_id" integer NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'planned',
 "summary" text,
 "non_conformities_found" int NOT NULL DEFAULT '0',
 "observations_found" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "quality_audits_auditor_id_foreign" ON "quality_audits" ("auditor_id");
CREATE INDEX "quality_audits_tid_idx" ON "quality_audits" ("tenant_id");
CREATE INDEX "quality_audits_tid_st_idx" ON "quality_audits" ("tenant_id","status");
CREATE INDEX "quality_audits_tenant_id_idx" ON "quality_audits" ("tenant_id");

CREATE TABLE "quality_corrective_actions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "quality_audit_id" integer NOT NULL,
 "quality_audit_item_id" integer DEFAULT NULL,
 "description" text NOT NULL,
 "root_cause" text,
 "action_taken" text,
 "responsible_id" integer DEFAULT NULL,
 "due_date" date DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "verified_at" datetime NULL DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'open',
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "quality_corrective_actions_tenant_id_index" ON "quality_corrective_actions" ("tenant_id");
CREATE INDEX "quality_corrective_actions_quality_audit_id_index" ON "quality_corrective_actions" ("quality_audit_id");
CREATE INDEX "quality_corrective_actions_quality_audit_item_id_index" ON "quality_corrective_actions" ("quality_audit_item_id");
CREATE INDEX "quality_corrective_actions_responsible_id_index" ON "quality_corrective_actions" ("responsible_id");
CREATE INDEX "quality_corrective_actions_tenant_id_idx" ON "quality_corrective_actions" ("tenant_id");

CREATE TABLE "quality_procedures" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "code" varchar(30) NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "revision" int NOT NULL DEFAULT '1',
 "category" varchar(50) DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "approved_at" date DEFAULT NULL,
 "next_review_date" date DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'active',
 "content" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "quality_procedures_approved_by_foreign" ON "quality_procedures" ("approved_by");
CREATE INDEX "quality_procedures_code_index" ON "quality_procedures" ("code");
CREATE INDEX "quality_procedures_tid_idx" ON "quality_procedures" ("tenant_id");
CREATE INDEX "quality_procedures_del_idx" ON "quality_procedures" ("deleted_at");
CREATE INDEX "quality_procedures_tid_st_idx" ON "quality_procedures" ("tenant_id","status");
CREATE INDEX "quality_procedures_tenant_id_idx" ON "quality_procedures" ("tenant_id");
CREATE INDEX "quality_procedures_deleted_at_idx" ON "quality_procedures" ("deleted_at");

CREATE TABLE "quick_notes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "deal_id" integer DEFAULT NULL,
 "channel" varchar(255) DEFAULT NULL,
 "sentiment" varchar(255) DEFAULT NULL,
 "content" text NOT NULL,
 "is_pinned" tinyint NOT NULL DEFAULT '0',
 "tags" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "quick_notes_customer_id_foreign" ON "quick_notes" ("customer_id");
CREATE INDEX "quick_notes_user_id_foreign" ON "quick_notes" ("user_id");
CREATE INDEX "quick_notes_deal_id_foreign" ON "quick_notes" ("deal_id");
CREATE INDEX "quick_notes_qn_tenant_cust_idx" ON "quick_notes" ("tenant_id","customer_id");
CREATE INDEX "quick_notes_qn_tenant_user_idx" ON "quick_notes" ("tenant_id","user_id");
CREATE INDEX "quick_notes_tenant_id_idx" ON "quick_notes" ("tenant_id");

CREATE TABLE "quote_approval_thresholds" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "min_value" numeric NOT NULL DEFAULT '0.00',
 "max_value" numeric DEFAULT NULL,
 "required_level" integer NOT NULL DEFAULT '1',
 "approver_role" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "quote_approval_thresholds_tenant_id_is_active_index" ON "quote_approval_thresholds" ("tenant_id","is_active");
CREATE INDEX "quote_approval_thresholds_tenant_id_idx" ON "quote_approval_thresholds" ("tenant_id");

CREATE TABLE "quote_emails" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "quote_id" integer NOT NULL,
 "sent_by" integer DEFAULT NULL,
 "recipient_email" varchar(255) NOT NULL,
 "recipient_name" varchar(255) DEFAULT NULL,
 "subject" varchar(255) NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'sent',
 "message_body" text,
 "pdf_attached" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "queued_at" datetime NULL DEFAULT NULL,
 "sent_at" datetime NULL DEFAULT NULL,
 "failed_at" datetime NULL DEFAULT NULL,
 "error_message" text
);
CREATE INDEX "quote_emails_quote_id_foreign" ON "quote_emails" ("quote_id");
CREATE INDEX "quote_emails_sent_by_foreign" ON "quote_emails" ("sent_by");
CREATE INDEX "quote_emails_tenant_id_quote_id_index" ON "quote_emails" ("tenant_id","quote_id");
CREATE INDEX "quote_emails_tenant_id_idx" ON "quote_emails" ("tenant_id");

CREATE TABLE "quote_equipments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "quote_id" integer NOT NULL,
 "equipment_id" integer DEFAULT NULL,
 "description" text,
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "quote_equipments_tenant_id_index" ON "quote_equipments" ("tenant_id");
CREATE INDEX "quote_equipments_quote_id_fk_idx" ON "quote_equipments" ("quote_id");
CREATE INDEX "quote_equipments_equipment_id_fk_idx" ON "quote_equipments" ("equipment_id");
CREATE INDEX "quote_equipments_tenant_id_idx" ON "quote_equipments" ("tenant_id");

CREATE TABLE "quote_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "quote_equipment_id" integer NOT NULL,
 "type" varchar(10) NOT NULL,
 "product_id" integer DEFAULT NULL,
 "service_id" integer DEFAULT NULL,
 "custom_description" varchar(255) DEFAULT NULL,
 "quantity" numeric NOT NULL DEFAULT '1.00',
 "original_price" numeric NOT NULL,
 "unit_price" numeric NOT NULL,
 "discount_percentage" numeric NOT NULL DEFAULT '0.00',
 "subtotal" numeric NOT NULL DEFAULT '0.00',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "cost_price" numeric NOT NULL DEFAULT '0.00',
 "internal_note" text,
 "quote_id" integer DEFAULT NULL,
 "total" numeric DEFAULT NULL
);
CREATE INDEX "quote_items_quote_equipment_id_foreign" ON "quote_items" ("quote_equipment_id");
CREATE INDEX "quote_items_tenant_id_index" ON "quote_items" ("tenant_id");
CREATE INDEX "quote_items_product_id_fk_idx" ON "quote_items" ("product_id");
CREATE INDEX "quote_items_service_id_fk_idx" ON "quote_items" ("service_id");
CREATE INDEX "quote_items_tenant_id_idx" ON "quote_items" ("tenant_id");

CREATE TABLE "quote_photos" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "quote_equipment_id" integer DEFAULT NULL,
 "quote_item_id" integer DEFAULT NULL,
 "path" varchar(255) NOT NULL,
 "caption" varchar(255) DEFAULT NULL,
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "quote_photos_quote_equipment_id_foreign" ON "quote_photos" ("quote_equipment_id");
CREATE INDEX "quote_photos_quote_item_id_foreign" ON "quote_photos" ("quote_item_id");
CREATE INDEX "quote_photos_tenant_id_index" ON "quote_photos" ("tenant_id");
CREATE INDEX "quote_photos_tenant_id_idx" ON "quote_photos" ("tenant_id");

CREATE TABLE "quote_quote_tag" (
 "quote_id" integer NOT NULL,
 "quote_tag_id" integer NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 PRIMARY KEY ("quote_id","quote_tag_id")
);
CREATE INDEX "quote_quote_tag_quote_tag_id_foreign" ON "quote_quote_tag" ("quote_tag_id");
CREATE INDEX "quote_quote_tag_tenant_idx" ON "quote_quote_tag" ("tenant_id");
CREATE INDEX "quote_quote_tag_tenant_id_idx" ON "quote_quote_tag" ("tenant_id");

CREATE TABLE "quote_sources" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "quote_sources_tid_slug_uq" ON "quote_sources" ("tenant_id","slug");
CREATE INDEX "quote_sources_tid_idx" ON "quote_sources" ("tenant_id");
CREATE INDEX "quote_sources_del_idx" ON "quote_sources" ("deleted_at");
CREATE INDEX "quote_sources_deleted_at_idx" ON "quote_sources" ("deleted_at");

CREATE TABLE "quote_tags" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "color" varchar(7) NOT NULL DEFAULT '#3b82f6',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "quote_tags_tenant_id_name_unique" ON "quote_tags" ("tenant_id","name");

CREATE TABLE "quote_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "warranty_terms" text,
 "payment_terms_text" text,
 "general_conditions" text,
 "delivery_terms" text,
 "is_default" tinyint NOT NULL DEFAULT '0',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "quote_templates_tenant_id_is_active_index" ON "quote_templates" ("tenant_id","is_active");
CREATE INDEX "quote_templates_tenant_id_idx" ON "quote_templates" ("tenant_id");

CREATE TABLE "quotes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "quote_number" varchar(30) NOT NULL,
 "customer_id" integer NOT NULL,
 "seller_id" integer DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'draft',
 "valid_until" date DEFAULT NULL,
 "discount_percentage" numeric NOT NULL DEFAULT '0.00',
 "discount_amount" numeric NOT NULL DEFAULT '0.00',
 "subtotal" numeric NOT NULL DEFAULT '0.00',
 "total" numeric NOT NULL DEFAULT '0.00',
 "observations" text,
 "internal_notes" text,
 "sent_at" datetime NULL DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "rejected_at" datetime NULL DEFAULT NULL,
 "rejection_reason" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "revision" integer NOT NULL DEFAULT '1',
 "internal_approved_by" integer DEFAULT NULL,
 "internal_approved_at" datetime NULL DEFAULT NULL,
 "source" varchar(50) DEFAULT NULL,
 "title" varchar(200) DEFAULT NULL,
 "parent_quote_id" integer DEFAULT NULL,
 "option_label" varchar(255) DEFAULT NULL,
 "version_number" int NOT NULL DEFAULT '1',
 "viewed_at" datetime NULL DEFAULT NULL,
 "view_count" int NOT NULL DEFAULT '0',
 "loss_reason" varchar(50) DEFAULT NULL,
 "loss_notes" text,
 "win_reason" varchar(50) DEFAULT NULL,
 "competitor_price" numeric DEFAULT NULL,
 "competitor_name" varchar(100) DEFAULT NULL,
 "price_table_id" integer DEFAULT NULL,
 "displacement_value" numeric NOT NULL DEFAULT '0.00',
 "signature_token" varchar(64) DEFAULT NULL,
 "signature_sent_at" datetime NULL DEFAULT NULL,
 "signed_at" datetime NULL DEFAULT NULL,
 "signer_name" varchar(255) DEFAULT NULL,
 "signer_document" varchar(20) DEFAULT NULL,
 "signature_data" text,
 "signer_ip" varchar(45) DEFAULT NULL,
 "payment_terms" varchar(50) DEFAULT NULL,
 "payment_terms_detail" text,
 "general_conditions" text,
 "template_id" integer DEFAULT NULL,
 "is_template" tinyint NOT NULL DEFAULT '0',
 "opportunity_id" integer DEFAULT NULL,
 "currency" varchar(3) NOT NULL DEFAULT 'BRL',
 "last_followup_at" datetime NULL DEFAULT NULL,
 "followup_count" integer NOT NULL DEFAULT '0',
 "client_viewed_at" datetime NULL DEFAULT NULL,
 "client_view_count" integer NOT NULL DEFAULT '0',
 "level2_approved_by" integer DEFAULT NULL,
 "level2_approved_at" datetime NULL DEFAULT NULL,
 "custom_fields" text DEFAULT NULL,
 "magic_token" varchar(64) DEFAULT NULL,
 "client_ip_approval" varchar(255) DEFAULT NULL,
 "term_accepted_at" datetime NULL DEFAULT NULL,
 "is_installation_testing" tinyint NOT NULL DEFAULT '0',
 "approval_channel" varchar(30) DEFAULT NULL,
 "approval_notes" text,
 "approved_by_name" varchar(100) DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "discount" numeric DEFAULT NULL,
 "validity_days" int DEFAULT NULL
);
CREATE UNIQUE INDEX "quotes_tenant_id_quote_number_unique" ON "quotes" ("tenant_id","quote_number");
CREATE UNIQUE INDEX "quotes_magic_token_unique" ON "quotes" ("magic_token");
CREATE INDEX "quotes_customer_id_foreign" ON "quotes" ("customer_id");
CREATE INDEX "quotes_tenant_id_status_index" ON "quotes" ("tenant_id","status");
CREATE INDEX "quotes_internal_approved_by_foreign" ON "quotes" ("internal_approved_by");
CREATE INDEX "quotes_price_table_id_foreign" ON "quotes" ("price_table_id");
CREATE INDEX "quotes_tenant_id_customer_id_index" ON "quotes" ("tenant_id","customer_id");
CREATE INDEX "quotes_template_id_foreign" ON "quotes" ("template_id");
CREATE INDEX "quotes_opportunity_id_foreign" ON "quotes" ("opportunity_id");
CREATE INDEX "quotes_qt_tenant_created_idx" ON "quotes" ("tenant_id","created_at");
CREATE INDEX "quotes_created_by_foreign" ON "quotes" ("created_by");
CREATE INDEX "quotes_qt_tenant_status" ON "quotes" ("tenant_id","status");
CREATE INDEX "quotes_qt_tenant_customer" ON "quotes" ("tenant_id","customer_id");
CREATE INDEX "quotes_qt_tenant_seller" ON "quotes" ("tenant_id","seller_id");
CREATE INDEX "quotes_qt_deleted_at" ON "quotes" ("tenant_id","deleted_at");
CREATE INDEX "quotes_del_idx" ON "quotes" ("deleted_at");
CREATE INDEX "quotes_seller_id_fk_idx" ON "quotes" ("seller_id");
CREATE INDEX "quotes_parent_quote_id_fk_idx" ON "quotes" ("parent_quote_id");
CREATE INDEX "quotes_level2_approved_by_foreign" ON "quotes" ("level2_approved_by");
CREATE INDEX "quotes_deleted_at_idx" ON "quotes" ("deleted_at");

CREATE TABLE "raw_data_backups" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "scope" varchar(255) NOT NULL,
 "date_from" date DEFAULT NULL,
 "date_to" date DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "file_path" varchar(255) DEFAULT NULL,
 "requested_by" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "raw_data_backups_tid_idx" ON "raw_data_backups" ("tenant_id");
CREATE INDEX "raw_data_backups_tenant_id_idx" ON "raw_data_backups" ("tenant_id");

CREATE TABLE "recall_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'sent',
 "created_at" datetime NOT NULL
);
CREATE INDEX "recall_logs_equipment_id_foreign" ON "recall_logs" ("equipment_id");
CREATE INDEX "recall_logs_customer_id_foreign" ON "recall_logs" ("customer_id");
CREATE INDEX "recall_logs_tid_idx" ON "recall_logs" ("tenant_id");
CREATE INDEX "recall_logs_tid_st_idx" ON "recall_logs" ("tenant_id","status");
CREATE INDEX "recall_logs_tenant_id_idx" ON "recall_logs" ("tenant_id");

CREATE TABLE "reconciliation_rules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "match_field" varchar NOT NULL DEFAULT 'description',
 "match_operator" varchar NOT NULL DEFAULT 'contains',
 "match_value" varchar(255) DEFAULT NULL,
 "match_amount_min" numeric DEFAULT NULL,
 "match_amount_max" numeric DEFAULT NULL,
 "action" varchar NOT NULL DEFAULT 'categorize',
 "target_type" varchar(255) DEFAULT NULL,
 "target_id" integer DEFAULT NULL,
 "category" varchar(255) DEFAULT NULL,
 "customer_id" integer DEFAULT NULL,
 "supplier_id" integer DEFAULT NULL,
 "priority" integer NOT NULL DEFAULT '10',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "times_applied" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "reconciliation_rules_customer_id_foreign" ON "reconciliation_rules" ("customer_id");
CREATE INDEX "reconciliation_rules_supplier_id_foreign" ON "reconciliation_rules" ("supplier_id");
CREATE INDEX "reconciliation_rules_tenant_id_is_active_priority_index" ON "reconciliation_rules" ("tenant_id","is_active","priority");
CREATE INDEX "reconciliation_rules_target_id_index" ON "reconciliation_rules" ("target_id");
CREATE INDEX "reconciliation_rules_tenant_id_idx" ON "reconciliation_rules" ("tenant_id");

CREATE TABLE "recurring_commissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "recurring_contract_id" integer NOT NULL,
 "commission_rule_id" integer NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'active',
 "last_generated_at" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "frequency" varchar(20) DEFAULT NULL
);
CREATE INDEX "recurring_commissions_commission_rule_id_foreign" ON "recurring_commissions" ("commission_rule_id");
CREATE INDEX "recurring_commissions_recurring_contract_id_foreign" ON "recurring_commissions" ("recurring_contract_id");
CREATE INDEX "recurring_commissions_tenant_id_status_index" ON "recurring_commissions" ("tenant_id","status");
CREATE INDEX "recurring_commissions_user_id_foreign" ON "recurring_commissions" ("user_id");
CREATE INDEX "recurring_commissions_tenant_id_idx" ON "recurring_commissions" ("tenant_id");

CREATE TABLE "recurring_contract_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "recurring_contract_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "description" varchar(255) NOT NULL,
 "quantity" numeric NOT NULL DEFAULT '1.00',
 "unit_price" numeric NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "recurring_contract_items_tenant_id_index" ON "recurring_contract_items" ("tenant_id");
CREATE INDEX "recurring_contract_items_recurring_contract_i_fk_idx" ON "recurring_contract_items" ("recurring_contract_id");
CREATE INDEX "recurring_contract_items_tenant_id_idx" ON "recurring_contract_items" ("tenant_id");

CREATE TABLE "recurring_contracts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "equipment_id" integer DEFAULT NULL,
 "assigned_to" integer DEFAULT NULL,
 "created_by" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "frequency" varchar NOT NULL,
 "start_date" date NOT NULL,
 "end_date" date DEFAULT NULL,
 "next_run_date" date NOT NULL,
 "priority" varchar(255) NOT NULL DEFAULT 'normal',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "generated_count" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "billing_type" varchar(20) NOT NULL DEFAULT 'per_os',
 "monthly_value" numeric NOT NULL DEFAULT '0.00',
 "adjustment_index" varchar(255) DEFAULT NULL,
 "next_adjustment_date" date DEFAULT NULL
);
CREATE INDEX "recurring_contracts_equipment_id_foreign" ON "recurring_contracts" ("equipment_id");
CREATE INDEX "recurring_contracts_assigned_to_foreign" ON "recurring_contracts" ("assigned_to");
CREATE INDEX "recurring_contracts_created_by_foreign" ON "recurring_contracts" ("created_by");
CREATE INDEX "recurring_contracts_rc_customer" ON "recurring_contracts" ("customer_id");
CREATE INDEX "recurring_contracts_rcon_deleted_at" ON "recurring_contracts" ("tenant_id","deleted_at");
CREATE INDEX "recurring_contracts_tid_idx" ON "recurring_contracts" ("tenant_id");
CREATE INDEX "recurring_contracts_del_idx" ON "recurring_contracts" ("deleted_at");
CREATE INDEX "recurring_contracts_tenant_id_idx" ON "recurring_contracts" ("tenant_id");
CREATE INDEX "recurring_contracts_deleted_at_idx" ON "recurring_contracts" ("deleted_at");

CREATE TABLE "referral_codes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "referrer_id" integer NOT NULL,
 "code" varchar(10) NOT NULL,
 "uses" int NOT NULL DEFAULT '0',
 "reward_type" varchar(20) NOT NULL DEFAULT 'discount',
 "reward_value" numeric NOT NULL DEFAULT '10.00',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NOT NULL
);
CREATE UNIQUE INDEX "referral_codes_code_unique" ON "referral_codes" ("code");
CREATE INDEX "referral_codes_tenant_id_index" ON "referral_codes" ("tenant_id");
CREATE INDEX "referral_codes_referrer_id_index" ON "referral_codes" ("referrer_id");
CREATE INDEX "referral_codes_tenant_id_idx" ON "referral_codes" ("tenant_id");

CREATE TABLE "repair_seal_alerts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "seal_id" integer NOT NULL,
 "technician_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "alert_type" varchar(20) NOT NULL,
 "severity" varchar(10) NOT NULL,
 "message" text NOT NULL,
 "acknowledged_at" datetime NULL DEFAULT NULL,
 "acknowledged_by" integer DEFAULT NULL,
 "resolved_at" datetime NULL DEFAULT NULL,
 "resolved_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "repair_seal_alerts_seal_id_foreign" ON "repair_seal_alerts" ("seal_id");
CREATE INDEX "repair_seal_alerts_technician_id_foreign" ON "repair_seal_alerts" ("technician_id");
CREATE INDEX "repair_seal_alerts_work_order_id_foreign" ON "repair_seal_alerts" ("work_order_id");
CREATE INDEX "repair_seal_alerts_acknowledged_by_foreign" ON "repair_seal_alerts" ("acknowledged_by");
CREATE INDEX "repair_seal_alerts_resolved_by_foreign" ON "repair_seal_alerts" ("resolved_by");
CREATE INDEX "repair_seal_alerts_idx_alerts_tech_resolved" ON "repair_seal_alerts" ("tenant_id","technician_id","resolved_at");
CREATE INDEX "repair_seal_alerts_idx_alerts_type_resolved" ON "repair_seal_alerts" ("tenant_id","alert_type","resolved_at");
CREATE INDEX "repair_seal_alerts_tenant_id_idx" ON "repair_seal_alerts" ("tenant_id");

CREATE TABLE "repair_seal_assignments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "seal_id" integer NOT NULL,
 "technician_id" integer NOT NULL,
 "assigned_by" integer NOT NULL,
 "action" varchar(20) NOT NULL,
 "previous_technician_id" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "repair_seal_assignments_seal_id_foreign" ON "repair_seal_assignments" ("seal_id");
CREATE INDEX "repair_seal_assignments_technician_id_foreign" ON "repair_seal_assignments" ("technician_id");
CREATE INDEX "repair_seal_assignments_assigned_by_foreign" ON "repair_seal_assignments" ("assigned_by");
CREATE INDEX "repair_seal_assignments_previous_technician_id_foreign" ON "repair_seal_assignments" ("previous_technician_id");
CREATE INDEX "repair_seal_assignments_tenant_id_seal_id_index" ON "repair_seal_assignments" ("tenant_id","seal_id");
CREATE INDEX "repair_seal_assignments_idx_assignments_tech_date" ON "repair_seal_assignments" ("tenant_id","technician_id","created_at");
CREATE INDEX "repair_seal_assignments_tenant_id_idx" ON "repair_seal_assignments" ("tenant_id");

CREATE TABLE "repair_seal_batches" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar NOT NULL,
 "batch_code" varchar(50) NOT NULL,
 "range_start" varchar(30) NOT NULL,
 "range_end" varchar(30) NOT NULL,
 "prefix" varchar(10) DEFAULT NULL,
 "suffix" varchar(10) DEFAULT NULL,
 "quantity" int NOT NULL,
 "quantity_available" int NOT NULL,
 "supplier" varchar(255) DEFAULT NULL,
 "invoice_number" varchar(255) DEFAULT NULL,
 "received_at" date NOT NULL,
 "received_by" integer NOT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "repair_seal_batches_tenant_id_batch_code_unique" ON "repair_seal_batches" ("tenant_id","batch_code");
CREATE INDEX "repair_seal_batches_received_by_foreign" ON "repair_seal_batches" ("received_by");
CREATE INDEX "repair_seal_batches_tenant_id_type_index" ON "repair_seal_batches" ("tenant_id","type");
CREATE INDEX "repair_seal_batches_deleted_at_idx" ON "repair_seal_batches" ("deleted_at");

CREATE TABLE "repeatability_tests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "equipment_calibration_id" integer NOT NULL,
 "load_value" numeric NOT NULL,
 "unit" varchar(10) NOT NULL DEFAULT 'kg',
 "measurement_1" numeric DEFAULT NULL,
 "measurement_2" numeric DEFAULT NULL,
 "measurement_3" numeric DEFAULT NULL,
 "measurement_4" numeric DEFAULT NULL,
 "measurement_5" numeric DEFAULT NULL,
 "measurement_6" numeric DEFAULT NULL,
 "measurement_7" numeric DEFAULT NULL,
 "measurement_8" numeric DEFAULT NULL,
 "measurement_9" numeric DEFAULT NULL,
 "measurement_10" numeric DEFAULT NULL,
 "mean" numeric DEFAULT NULL,
 "std_deviation" numeric DEFAULT NULL,
 "uncertainty_type_a" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "range_value" numeric DEFAULT NULL
);
CREATE INDEX "repeatability_tests_equipment_calibration_id_foreign" ON "repeatability_tests" ("equipment_calibration_id");
CREATE INDEX "repeatability_tests_tid_idx" ON "repeatability_tests" ("tenant_id");
CREATE INDEX "repeatability_tests_tenant_id_idx" ON "repeatability_tests" ("tenant_id");

CREATE TABLE "rescissions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "type" varchar(30) NOT NULL,
 "notice_date" date DEFAULT NULL,
 "termination_date" date NOT NULL,
 "last_work_day" date DEFAULT NULL,
 "notice_type" varchar(20) DEFAULT NULL,
 "notice_days" int NOT NULL DEFAULT '30',
 "notice_value" numeric NOT NULL DEFAULT '0.00',
 "salary_balance_days" int NOT NULL DEFAULT '0',
 "salary_balance_value" numeric NOT NULL DEFAULT '0.00',
 "vacation_proportional_days" int NOT NULL DEFAULT '0',
 "vacation_proportional_value" numeric NOT NULL DEFAULT '0.00',
 "vacation_bonus_value" numeric NOT NULL DEFAULT '0.00',
 "vacation_overdue_days" int NOT NULL DEFAULT '0',
 "vacation_overdue_value" numeric NOT NULL DEFAULT '0.00',
 "vacation_overdue_bonus_value" numeric NOT NULL DEFAULT '0.00',
 "thirteenth_proportional_months" int NOT NULL DEFAULT '0',
 "thirteenth_proportional_value" numeric NOT NULL DEFAULT '0.00',
 "fgts_balance" numeric NOT NULL DEFAULT '0.00',
 "fgts_penalty_value" numeric NOT NULL DEFAULT '0.00',
 "fgts_penalty_rate" numeric NOT NULL DEFAULT '40.00',
 "advance_deductions" numeric NOT NULL DEFAULT '0.00',
 "hour_bank_payout" numeric NOT NULL DEFAULT '0.00',
 "other_earnings" numeric NOT NULL DEFAULT '0.00',
 "other_deductions" numeric NOT NULL DEFAULT '0.00',
 "inss_deduction" numeric NOT NULL DEFAULT '0.00',
 "irrf_deduction" numeric NOT NULL DEFAULT '0.00',
 "total_gross" numeric NOT NULL DEFAULT '0.00',
 "total_deductions" numeric NOT NULL DEFAULT '0.00',
 "total_net" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(20) NOT NULL DEFAULT 'draft',
 "calculated_by" integer DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "calculated_at" datetime NULL DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "paid_at" datetime NULL DEFAULT NULL,
 "trct_file_path" varchar(255) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "rescissions_user_id_foreign" ON "rescissions" ("user_id");
CREATE INDEX "rescissions_calculated_by_foreign" ON "rescissions" ("calculated_by");
CREATE INDEX "rescissions_approved_by_foreign" ON "rescissions" ("approved_by");
CREATE INDEX "rescissions_tenant_id_user_id_index" ON "rescissions" ("tenant_id","user_id");
CREATE INDEX "rescissions_tenant_id_idx" ON "rescissions" ("tenant_id");

CREATE TABLE "retention_samples" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "sample_code" varchar(50) NOT NULL,
 "description" varchar(255) NOT NULL,
 "location" varchar(100) NOT NULL,
 "retention_days" int NOT NULL,
 "expires_at" date DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'stored',
 "stored_at" datetime NULL DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "retention_samples_work_order_id_foreign" ON "retention_samples" ("work_order_id");
CREATE INDEX "retention_samples_tid_idx" ON "retention_samples" ("tenant_id");
CREATE INDEX "retention_samples_tenant_id_idx" ON "retention_samples" ("tenant_id");

CREATE TABLE "returned_used_item_dispositions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "used_stock_item_id" integer NOT NULL,
 "sent_for_repair" tinyint NOT NULL DEFAULT '0',
 "repair_provider_id" integer DEFAULT NULL,
 "repair_provider_name" varchar(150) DEFAULT NULL,
 "repair_sent_at" datetime NULL DEFAULT NULL,
 "repair_returned_at" datetime NULL DEFAULT NULL,
 "will_discard" tinyint NOT NULL DEFAULT '0',
 "discarded_at" datetime NULL DEFAULT NULL,
 "disposition_notes" text,
 "registered_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "returned_used_item_dispositions_used_stock_item_id_foreign" ON "returned_used_item_dispositions" ("used_stock_item_id");
CREATE INDEX "returned_used_item_dispositions_registered_by_foreign" ON "returned_used_item_dispositions" ("registered_by");
CREATE INDEX "returned_used_item_dispositions_repair_provider_id_foreign" ON "returned_used_item_dispositions" ("repair_provider_id");

CREATE TABLE "rma_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "rma_request_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "defect_description" text,
 "condition" varchar NOT NULL DEFAULT 'defective',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "rma_items_rma_request_id_foreign" ON "rma_items" ("rma_request_id");
CREATE INDEX "rma_items_product_id_foreign" ON "rma_items" ("product_id");

CREATE TABLE "rma_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "rma_number" varchar(30) NOT NULL,
 "customer_id" integer DEFAULT NULL,
 "supplier_id" integer DEFAULT NULL,
 "type" varchar NOT NULL DEFAULT 'customer_return',
 "status" varchar NOT NULL DEFAULT 'requested',
 "reason" text NOT NULL,
 "resolution_notes" text,
 "resolution" varchar DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "rma_requests_rma_number_unique" ON "rma_requests" ("rma_number");
CREATE INDEX "rma_requests_tenant_id_index" ON "rma_requests" ("tenant_id");
CREATE INDEX "rma_requests_customer_id_foreign" ON "rma_requests" ("customer_id");
CREATE INDEX "rma_requests_supplier_id_foreign" ON "rma_requests" ("supplier_id");
CREATE INDEX "rma_requests_work_order_id_foreign" ON "rma_requests" ("work_order_id");
CREATE INDEX "rma_requests_del_idx" ON "rma_requests" ("deleted_at");
CREATE INDEX "rma_requests_tid_st_idx" ON "rma_requests" ("tenant_id","status");
CREATE INDEX "rma_requests_tenant_id_idx" ON "rma_requests" ("tenant_id");
CREATE INDEX "rma_requests_deleted_at_idx" ON "rma_requests" ("deleted_at");

CREATE TABLE "role_has_permissions" (
 "permission_id" integer NOT NULL,
 "role_id" integer NOT NULL,
 PRIMARY KEY ("permission_id","role_id")
);
CREATE INDEX "role_has_permissions_role_id_foreign" ON "role_has_permissions" ("role_id");

CREATE TABLE "roles" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "guard_name" varchar(255) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "description" varchar(500) DEFAULT NULL,
 "display_name" varchar(150) DEFAULT NULL
);
CREATE UNIQUE INDEX "roles_tenant_id_name_guard_name_unique" ON "roles" ("tenant_id","name","guard_name");
CREATE UNIQUE INDEX "roles_name_guard_name_tenant_id_unique" ON "roles" ("name","guard_name","tenant_id");
CREATE INDEX "roles_team_foreign_key_index" ON "roles" ("tenant_id");

CREATE TABLE "route_plans" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "technician_id" integer NOT NULL,
 "plan_date" date NOT NULL,
 "stops" text DEFAULT NULL,
 "total_distance_km" numeric DEFAULT NULL,
 "estimated_duration_min" int DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'planned',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "route_plans_technician_id_foreign" ON "route_plans" ("technician_id");
CREATE INDEX "route_plans_tid_idx" ON "route_plans" ("tenant_id");
CREATE INDEX "route_plans_tenant_id_idx" ON "route_plans" ("tenant_id");

CREATE TABLE "routes_planning" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "tech_id" integer NOT NULL,
 "vehicle_id" integer DEFAULT NULL,
 "date" date NOT NULL,
 "optimized_path_json" text DEFAULT NULL,
 "total_distance_km" numeric DEFAULT NULL,
 "estimated_fuel_liters" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "routes_planning_tech_id_foreign" ON "routes_planning" ("tech_id");
CREATE INDEX "routes_planning_vehicle_id_foreign" ON "routes_planning" ("vehicle_id");
CREATE INDEX "routes_planning_tid_idx" ON "routes_planning" ("tenant_id");
CREATE INDEX "routes_planning_tenant_id_idx" ON "routes_planning" ("tenant_id");

CREATE TABLE "rr_studies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "instrument_id" integer DEFAULT NULL,
 "parameter" varchar(255) DEFAULT NULL,
 "operators" text DEFAULT NULL,
 "repetitions" int NOT NULL DEFAULT '0',
 "status" varchar(255) NOT NULL DEFAULT 'draft',
 "results" text DEFAULT NULL,
 "conclusion" text,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "rr_studies_created_by_foreign" ON "rr_studies" ("created_by");
CREATE INDEX "rr_studies_tenant_id_index" ON "rr_studies" ("tenant_id");
CREATE INDEX "rr_studies_instrument_id_index" ON "rr_studies" ("instrument_id");
CREATE INDEX "rr_studies_tenant_id_idx" ON "rr_studies" ("tenant_id");

CREATE TABLE "saas_plans" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" text,
 "monthly_price" numeric NOT NULL DEFAULT '0.00',
 "annual_price" numeric NOT NULL DEFAULT '0.00',
 "modules" text DEFAULT NULL,
 "max_users" int NOT NULL DEFAULT '5',
 "max_work_orders_month" int DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "saas_plans_slug_unique" ON "saas_plans" ("slug");

CREATE TABLE "saas_subscriptions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "plan_id" integer NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'trial',
 "billing_cycle" varchar(255) NOT NULL DEFAULT 'monthly',
 "price" numeric NOT NULL,
 "discount" numeric NOT NULL DEFAULT '0.00',
 "started_at" date NOT NULL,
 "trial_ends_at" date DEFAULT NULL,
 "current_period_start" date NOT NULL,
 "current_period_end" date NOT NULL,
 "cancelled_at" date DEFAULT NULL,
 "cancellation_reason" varchar(255) DEFAULT NULL,
 "payment_gateway" varchar(255) DEFAULT NULL,
 "gateway_subscription_id" varchar(255) DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "saas_subscriptions_plan_id_foreign" ON "saas_subscriptions" ("plan_id");
CREATE INDEX "saas_subscriptions_created_by_foreign" ON "saas_subscriptions" ("created_by");
CREATE INDEX "saas_subscriptions_tenant_id_idx" ON "saas_subscriptions" ("tenant_id");

CREATE TABLE "satisfaction_surveys" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "nps_score" tinyint DEFAULT NULL,
 "service_rating" tinyint DEFAULT NULL,
 "technician_rating" tinyint DEFAULT NULL,
 "timeliness_rating" tinyint DEFAULT NULL,
 "comment" text,
 "channel" varchar(30) NOT NULL DEFAULT 'system',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "satisfaction_surveys_customer_id_foreign" ON "satisfaction_surveys" ("customer_id");
CREATE INDEX "satisfaction_surveys_work_order_id_foreign" ON "satisfaction_surveys" ("work_order_id");
CREATE INDEX "satisfaction_surveys_tid_idx" ON "satisfaction_surveys" ("tenant_id");
CREATE INDEX "satisfaction_surveys_tenant_id_idx" ON "satisfaction_surveys" ("tenant_id");

CREATE TABLE "scale_readings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "scale_identifier" varchar(50) NOT NULL,
 "reading_value" numeric NOT NULL,
 "unit" varchar(10) NOT NULL,
 "reference_weight" numeric DEFAULT NULL,
 "error" numeric DEFAULT NULL,
 "interface_type" varchar(255) NOT NULL,
 "reading_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "scale_readings_tenant_id_work_order_id_index" ON "scale_readings" ("tenant_id","work_order_id");
CREATE INDEX "scale_readings_work_order_id_foreign" ON "scale_readings" ("work_order_id");
CREATE INDEX "scale_readings_tenant_id_idx" ON "scale_readings" ("tenant_id");

CREATE TABLE "scheduled_appointments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "scheduled_at" datetime NOT NULL,
 "service_type" varchar(100) NOT NULL,
 "notes" text,
 "status" varchar(30) NOT NULL DEFAULT 'confirmed',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "scheduled_appointments_tenant_id_index" ON "scheduled_appointments" ("tenant_id");
CREATE INDEX "scheduled_appointments_customer_id_index" ON "scheduled_appointments" ("customer_id");
CREATE INDEX "scheduled_appointments_tenant_id_idx" ON "scheduled_appointments" ("tenant_id");

CREATE TABLE "scheduled_report_exports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "report_type" varchar(255) NOT NULL,
 "format" varchar(255) NOT NULL DEFAULT 'xlsx',
 "frequency" varchar(255) NOT NULL,
 "recipients" text NOT NULL,
 "filters" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_by" integer NOT NULL,
 "last_sent_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "scheduled_report_exports_created_by_foreign" ON "scheduled_report_exports" ("created_by");
CREATE INDEX "scheduled_report_exports_tid_idx" ON "scheduled_report_exports" ("tenant_id");
CREATE INDEX "scheduled_report_exports_tenant_id_idx" ON "scheduled_report_exports" ("tenant_id");

CREATE TABLE "scheduled_reports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "report_type" varchar(50) NOT NULL,
 "frequency" varchar(20) NOT NULL,
 "recipients" text DEFAULT NULL,
 "filters" text DEFAULT NULL,
 "format" varchar(10) NOT NULL DEFAULT 'pdf',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "last_sent_at" date DEFAULT NULL,
 "next_send_at" date DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "name" varchar(255) DEFAULT NULL
);
CREATE INDEX "scheduled_reports_created_by_foreign" ON "scheduled_reports" ("created_by");
CREATE INDEX "scheduled_reports_tid_idx" ON "scheduled_reports" ("tenant_id");
CREATE INDEX "scheduled_reports_tenant_id_idx" ON "scheduled_reports" ("tenant_id");

CREATE TABLE "schedules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "customer_id" integer DEFAULT NULL,
 "technician_id" integer DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "notes" text,
 "scheduled_start" datetime NOT NULL,
 "scheduled_end" datetime NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'scheduled',
 "address" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "schedules_tenant_id_technician_id_scheduled_start_index" ON "schedules" ("tenant_id","technician_id","scheduled_start");
CREATE INDEX "schedules_sched_tenant_status" ON "schedules" ("tenant_id","status");
CREATE INDEX "schedules_sched_tenant_customer" ON "schedules" ("tenant_id","customer_id");
CREATE INDEX "schedules_sched_work_order" ON "schedules" ("work_order_id");
CREATE INDEX "schedules_technician_id_foreign" ON "schedules" ("technician_id");
CREATE INDEX "schedules_sched_deleted_at" ON "schedules" ("deleted_at");
CREATE INDEX "schedules_del_idx" ON "schedules" ("deleted_at");
CREATE INDEX "schedules_work_order_id_fk_idx" ON "schedules" ("work_order_id");
CREATE INDEX "schedules_customer_id_fk_idx" ON "schedules" ("customer_id");
CREATE INDEX "schedules_tid_st_idx" ON "schedules" ("tenant_id","status");
CREATE INDEX "schedules_tenant_id_idx" ON "schedules" ("tenant_id");
CREATE INDEX "schedules_deleted_at_idx" ON "schedules" ("deleted_at");

CREATE TABLE "seal_applications" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "equipment_id" integer DEFAULT NULL,
 "seal_number" varchar(100) NOT NULL,
 "location" varchar(255) DEFAULT NULL,
 "applied_by" integer NOT NULL,
 "applied_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "seal_applications_tenant_id_work_order_id_index" ON "seal_applications" ("tenant_id","work_order_id");
CREATE INDEX "seal_applications_tenant_id_index" ON "seal_applications" ("tenant_id");
CREATE INDEX "seal_applications_work_order_id_index" ON "seal_applications" ("work_order_id");
CREATE INDEX "seal_applications_equipment_id_index" ON "seal_applications" ("equipment_id");
CREATE INDEX "seal_applications_tenant_id_idx" ON "seal_applications" ("tenant_id");

CREATE TABLE "search_index" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "searchable_type" varchar(255) NOT NULL,
 "searchable_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "content" text,
 "module" varchar(255) NOT NULL,
 "url" varchar(255) DEFAULT NULL,
 "indexed_at" datetime NOT NULL
);
CREATE INDEX "search_index_idx_search_tenant_type_id" ON "search_index" ("tenant_id","searchable_type","searchable_id");
CREATE INDEX "search_index_tenant_id_idx" ON "search_index" ("tenant_id");

CREATE TABLE "self_service_quote_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_name" varchar(255) NOT NULL,
 "customer_email" varchar(255) NOT NULL,
 "customer_phone" varchar(255) NOT NULL,
 "items" text NOT NULL,
 "notes" text,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "created_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "self_service_quote_requests_tenant_id_status_index" ON "self_service_quote_requests" ("tenant_id","status");
CREATE INDEX "self_service_quote_requests_tenant_id_idx" ON "self_service_quote_requests" ("tenant_id");

CREATE TABLE "sensor_readings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "sensor_id" varchar(50) NOT NULL,
 "sensor_type" varchar(255) NOT NULL,
 "value" numeric NOT NULL,
 "unit" varchar(10) NOT NULL,
 "location" varchar(100) DEFAULT NULL,
 "reading_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "sensor_readings_idx_lab_sensor_reading" ON "sensor_readings" ("tenant_id","sensor_id","reading_at");
CREATE INDEX "sensor_readings_tenant_id_idx" ON "sensor_readings" ("tenant_id");

CREATE TABLE "serial_numbers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "serial" varchar(100) NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'available',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "batch_number" varchar(100) DEFAULT NULL,
 "location" varchar(255) DEFAULT NULL
);
CREATE UNIQUE INDEX "serial_numbers_tenant_id_serial_unique" ON "serial_numbers" ("tenant_id","serial");
CREATE INDEX "serial_numbers_product_id_foreign" ON "serial_numbers" ("product_id");

CREATE TABLE "service_call_comments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "service_call_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "content" text NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "service_call_comments_service_call_id_index" ON "service_call_comments" ("service_call_id");
CREATE INDEX "service_call_comments_scc_tenant_sc_idx" ON "service_call_comments" ("tenant_id","service_call_id");
CREATE INDEX "service_call_comments_user_id_fk_idx" ON "service_call_comments" ("user_id");
CREATE INDEX "service_call_comments_tenant_id_idx" ON "service_call_comments" ("tenant_id");

CREATE TABLE "service_call_equipments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "service_call_id" integer NOT NULL,
 "equipment_id" integer NOT NULL,
 "observations" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE INDEX "service_call_equipments_equipment_id_foreign" ON "service_call_equipments" ("equipment_id");
CREATE INDEX "service_call_equipments_service_call_equip_tenant_idx" ON "service_call_equipments" ("tenant_id");
CREATE INDEX "service_call_equipments_sce_service_call" ON "service_call_equipments" ("service_call_id");
CREATE INDEX "service_call_equipments_tenant_id_idx" ON "service_call_equipments" ("tenant_id");

CREATE TABLE "service_call_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "priority" varchar(10) NOT NULL DEFAULT 'normal',
 "observations" text,
 "equipment_ids" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "service_call_templates_tenant_id_is_active_index" ON "service_call_templates" ("tenant_id","is_active");
CREATE INDEX "service_call_templates_tenant_id_idx" ON "service_call_templates" ("tenant_id");

CREATE TABLE "service_calls" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "call_number" varchar(30) NOT NULL,
 "customer_id" integer NOT NULL,
 "quote_id" integer DEFAULT NULL,
 "technician_id" integer DEFAULT NULL,
 "driver_id" integer DEFAULT NULL,
 "status" varchar(25) NOT NULL DEFAULT 'open',
 "priority" varchar(10) NOT NULL DEFAULT 'normal',
 "scheduled_date" datetime DEFAULT NULL,
 "started_at" datetime DEFAULT NULL,
 "completed_at" datetime DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "address" varchar(255) DEFAULT NULL,
 "city" varchar(255) DEFAULT NULL,
 "state" varchar(2) DEFAULT NULL,
 "observations" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "resolution_notes" text,
 "reschedule_count" int NOT NULL DEFAULT '0',
 "reschedule_reason" text,
 "contract_id" integer DEFAULT NULL,
 "sla_policy_id" integer DEFAULT NULL,
 "reschedule_history" text DEFAULT NULL,
 "template_id" integer DEFAULT NULL,
 "sla_due_at" datetime NULL DEFAULT NULL,
 "sla_response_breached" tinyint NOT NULL DEFAULT '0',
 "sla_resolution_breached" tinyint NOT NULL DEFAULT '0',
 "google_maps_link" varchar(500) DEFAULT NULL
);
CREATE INDEX "service_calls_customer_id_foreign" ON "service_calls" ("customer_id");
CREATE INDEX "service_calls_driver_id_foreign" ON "service_calls" ("driver_id");
CREATE INDEX "service_calls_tenant_id_status_index" ON "service_calls" ("tenant_id","status");
CREATE INDEX "service_calls_created_by_foreign" ON "service_calls" ("created_by");
CREATE INDEX "service_calls_tenant_id_customer_id_index" ON "service_calls" ("tenant_id","customer_id");
CREATE INDEX "service_calls_tenant_id_scheduled_date_index" ON "service_calls" ("tenant_id","scheduled_date");
CREATE INDEX "service_calls_sla_policy_id_foreign" ON "service_calls" ("sla_policy_id");
CREATE INDEX "service_calls_template_id_foreign" ON "service_calls" ("template_id");
CREATE INDEX "service_calls_sc_tenant_tech_status" ON "service_calls" ("tenant_id","technician_id","status");
CREATE INDEX "service_calls_sc_tenant_customer" ON "service_calls" ("tenant_id","customer_id");
CREATE INDEX "service_calls_sc_tenant_driver" ON "service_calls" ("tenant_id","driver_id");
CREATE INDEX "service_calls_sc_deleted_at" ON "service_calls" ("tenant_id","deleted_at");
CREATE INDEX "service_calls_del_idx" ON "service_calls" ("deleted_at");
CREATE INDEX "service_calls_quote_id_fk_idx" ON "service_calls" ("quote_id");
CREATE INDEX "service_calls_technician_id_fk_idx" ON "service_calls" ("technician_id");
CREATE INDEX "service_calls_contract_id_fk_idx" ON "service_calls" ("contract_id");
CREATE INDEX "service_calls_tenant_id_idx" ON "service_calls" ("tenant_id");
CREATE INDEX "service_calls_deleted_at_idx" ON "service_calls" ("deleted_at");

CREATE TABLE "service_catalog_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "service_catalog_id" integer NOT NULL,
 "service_id" integer DEFAULT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "image_path" varchar(255) DEFAULT NULL,
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "service_catalog_items_svc_cat_item_order_idx" ON "service_catalog_items" ("service_catalog_id","sort_order");
CREATE INDEX "service_catalog_items_service_id_foreign" ON "service_catalog_items" ("service_id");

CREATE TABLE "service_catalogs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(64) NOT NULL,
 "subtitle" varchar(255) DEFAULT NULL,
 "header_description" text,
 "is_published" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "service_catalogs_slug_unique" ON "service_catalogs" ("slug");
CREATE INDEX "service_catalogs_svc_cat_tenant_slug_idx" ON "service_catalogs" ("tenant_id","slug");
CREATE INDEX "service_catalogs_tenant_id_idx" ON "service_catalogs" ("tenant_id");

CREATE TABLE "service_categories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "service_categories_tenant_id_name_unique" ON "service_categories" ("tenant_id","name");
CREATE INDEX "service_categories_scat_tid_act_idx" ON "service_categories" ("tenant_id","is_active");

CREATE TABLE "service_checklist_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "checklist_id" integer NOT NULL,
 "description" text NOT NULL,
 "type" varchar(20) NOT NULL DEFAULT 'check',
 "is_required" tinyint NOT NULL DEFAULT '0',
 "order_index" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "service_checklist_items_checklist_id_foreign" ON "service_checklist_items" ("checklist_id");

CREATE TABLE "service_checklists" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "service_checklists_scl_tenant" ON "service_checklists" ("tenant_id");
CREATE INDEX "service_checklists_tid_idx" ON "service_checklists" ("tenant_id");
CREATE INDEX "service_checklists_tenant_id_idx" ON "service_checklists" ("tenant_id");

CREATE TABLE "service_skills" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "service_id" integer NOT NULL,
 "skill_id" integer NOT NULL,
 "required_level" int NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE UNIQUE INDEX "service_skills_service_id_skill_id_unique" ON "service_skills" ("service_id","skill_id");
CREATE INDEX "service_skills_skill_id_foreign" ON "service_skills" ("skill_id");
CREATE INDEX "service_skills_tenant_idx" ON "service_skills" ("tenant_id");
CREATE INDEX "service_skills_tenant_id_idx" ON "service_skills" ("tenant_id");

CREATE TABLE "service_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "service_types_tid_slug_uq" ON "service_types" ("tenant_id","slug");
CREATE INDEX "service_types_tid_idx" ON "service_types" ("tenant_id");
CREATE INDEX "service_types_del_idx" ON "service_types" ("deleted_at");
CREATE INDEX "service_types_deleted_at_idx" ON "service_types" ("deleted_at");

CREATE TABLE "services" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "category_id" integer DEFAULT NULL,
 "code" varchar(50) DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "default_price" numeric NOT NULL DEFAULT '0.00',
 "estimated_minutes" int DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "services_tenant_id_code_unique" ON "services" ("tenant_id","code");
CREATE INDEX "services_category_id_foreign" ON "services" ("category_id");
CREATE INDEX "services_tenant_id_name_index" ON "services" ("tenant_id","name");
CREATE INDEX "services_del_idx" ON "services" ("deleted_at");
CREATE INDEX "services_deleted_at_idx" ON "services" ("deleted_at");

CREATE TABLE "sessions" (
 "id" varchar(255) NOT NULL,
 "user_id" integer DEFAULT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "user_agent" text,
 "payload" text NOT NULL,
 "last_activity" int NOT NULL,
 PRIMARY KEY ("id")
);
CREATE INDEX "sessions_user_id_index" ON "sessions" ("user_id");
CREATE INDEX "sessions_last_activity_index" ON "sessions" ("last_activity");

CREATE TABLE "skill_requirements" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "position_id" integer NOT NULL,
 "skill_id" integer NOT NULL,
 "required_level" int NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "skill_requirements_position_id_foreign" ON "skill_requirements" ("position_id");
CREATE INDEX "skill_requirements_skill_id_foreign" ON "skill_requirements" ("skill_id");
CREATE INDEX "skill_requirements_tenant_id_index" ON "skill_requirements" ("tenant_id");
CREATE INDEX "skill_requirements_tenant_id_idx" ON "skill_requirements" ("tenant_id");

CREATE TABLE "skills" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "category" varchar(255) DEFAULT NULL,
 "description" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "skills_tid_idx" ON "skills" ("tenant_id");
CREATE INDEX "skills_tenant_id_idx" ON "skills" ("tenant_id");

CREATE TABLE "sla_policies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "response_time_minutes" int NOT NULL DEFAULT '60',
 "resolution_time_minutes" int NOT NULL DEFAULT '480',
 "priority" varchar(20) NOT NULL DEFAULT 'medium',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "sla_policies_sla_tenant_active" ON "sla_policies" ("tenant_id","is_active");
CREATE INDEX "sla_policies_tid_idx" ON "sla_policies" ("tenant_id");
CREATE INDEX "sla_policies_tenant_id_idx" ON "sla_policies" ("tenant_id");

CREATE TABLE "sla_violations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "portal_ticket_id" integer NOT NULL,
 "sla_policy_id" integer NOT NULL,
 "violation_type" varchar(255) NOT NULL,
 "violated_at" datetime NOT NULL,
 "minutes_exceeded" int NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "sla_violations_portal_ticket_id_foreign" ON "sla_violations" ("portal_ticket_id");
CREATE INDEX "sla_violations_sla_policy_id_foreign" ON "sla_violations" ("sla_policy_id");
CREATE INDEX "sla_violations_tenant_id_idx" ON "sla_violations" ("tenant_id");

CREATE TABLE "sso_configurations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "provider" varchar(20) NOT NULL,
 "client_id" text NOT NULL,
 "client_secret" text NOT NULL,
 "tenant_domain" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "sso_configurations_tenant_id_provider_unique" ON "sso_configurations" ("tenant_id","provider");
CREATE INDEX "sso_configurations_tenant_id_index" ON "sso_configurations" ("tenant_id");

CREATE TABLE "standard_weights" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "code" varchar(30) NOT NULL,
 "nominal_value" numeric NOT NULL,
 "unit" varchar(10) NOT NULL DEFAULT 'kg',
 "serial_number" varchar(100) DEFAULT NULL,
 "manufacturer" varchar(150) DEFAULT NULL,
 "precision_class" varchar(10) DEFAULT NULL,
 "material" varchar(100) DEFAULT NULL,
 "shape" varchar(50) DEFAULT NULL,
 "certificate_number" varchar(100) DEFAULT NULL,
 "certificate_date" date DEFAULT NULL,
 "certificate_expiry" date DEFAULT NULL,
 "certificate_file" varchar(500) DEFAULT NULL,
 "laboratory" varchar(200) DEFAULT NULL,
 "laboratory_accreditation" varchar(100) DEFAULT NULL,
 "traceability_chain" varchar(500) DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'active',
 "notes" text,
 "deleted_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "assigned_to_vehicle_id" integer DEFAULT NULL,
 "assigned_to_user_id" integer DEFAULT NULL,
 "current_location" varchar(255) DEFAULT NULL,
 "wear_rate_percentage" numeric DEFAULT NULL,
 "expected_failure_date" date DEFAULT NULL
);
CREATE UNIQUE INDEX "standard_weights_tenant_id_code_unique" ON "standard_weights" ("tenant_id","code");
CREATE INDEX "standard_weights_tenant_id_status_index" ON "standard_weights" ("tenant_id","status");
CREATE INDEX "standard_weights_tenant_id_certificate_expiry_index" ON "standard_weights" ("tenant_id","certificate_expiry");
CREATE INDEX "standard_weights_assigned_to_vehicle_id_foreign" ON "standard_weights" ("assigned_to_vehicle_id");
CREATE INDEX "standard_weights_assigned_to_user_id_foreign" ON "standard_weights" ("assigned_to_user_id");
CREATE INDEX "standard_weights_del_idx" ON "standard_weights" ("deleted_at");
CREATE INDEX "standard_weights_deleted_at_idx" ON "standard_weights" ("deleted_at");

CREATE TABLE "stock_demand_forecasts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "forecast_date" date NOT NULL,
 "predicted_demand" int NOT NULL DEFAULT '0',
 "scheduled_os_count" int NOT NULL DEFAULT '0',
 "current_stock" int NOT NULL DEFAULT '0',
 "deficit" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "stock_demand_forecasts_product_id_foreign" ON "stock_demand_forecasts" ("product_id");
CREATE INDEX "stock_demand_forecasts_tenant_id_forecast_date_index" ON "stock_demand_forecasts" ("tenant_id","forecast_date");
CREATE INDEX "stock_demand_forecasts_tenant_id_idx" ON "stock_demand_forecasts" ("tenant_id");

CREATE TABLE "stock_disposal_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "stock_disposal_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "unit_cost" numeric NOT NULL DEFAULT '0.0000',
 "batch_id" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "stock_disposal_items_stock_disposal_id_foreign" ON "stock_disposal_items" ("stock_disposal_id");
CREATE INDEX "stock_disposal_items_product_id_foreign" ON "stock_disposal_items" ("product_id");
CREATE INDEX "stock_disposal_items_batch_id_index" ON "stock_disposal_items" ("batch_id");

CREATE TABLE "stock_disposals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "reference" varchar(30) NOT NULL,
 "disposal_type" varchar NOT NULL DEFAULT 'other',
 "disposal_method" varchar NOT NULL DEFAULT 'recycling',
 "status" varchar NOT NULL DEFAULT 'pending',
 "justification" text NOT NULL,
 "environmental_notes" text,
 "disposal_certificate" varchar(255) DEFAULT NULL,
 "warehouse_id" integer DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "stock_disposals_reference_unique" ON "stock_disposals" ("reference");
CREATE INDEX "stock_disposals_tenant_id_index" ON "stock_disposals" ("tenant_id");
CREATE INDEX "stock_disposals_warehouse_id_foreign" ON "stock_disposals" ("warehouse_id");
CREATE INDEX "stock_disposals_del_idx" ON "stock_disposals" ("deleted_at");
CREATE INDEX "stock_disposals_tid_st_idx" ON "stock_disposals" ("tenant_id","status");
CREATE INDEX "stock_disposals_tenant_id_idx" ON "stock_disposals" ("tenant_id");
CREATE INDEX "stock_disposals_deleted_at_idx" ON "stock_disposals" ("deleted_at");

CREATE TABLE "stock_movements" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "type" varchar(20) NOT NULL,
 "quantity" numeric NOT NULL,
 "unit_cost" numeric NOT NULL DEFAULT '0.00',
 "reference" varchar(255) DEFAULT NULL,
 "notes" text,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "warehouse_id" integer DEFAULT NULL,
 "batch_id" integer DEFAULT NULL,
 "product_serial_id" integer DEFAULT NULL,
 "target_warehouse_id" integer DEFAULT NULL,
 "scanned_via_qr" tinyint NOT NULL DEFAULT '0',
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "stock_movements_product_id_foreign" ON "stock_movements" ("product_id");
CREATE INDEX "stock_movements_created_by_foreign" ON "stock_movements" ("created_by");
CREATE INDEX "stock_movements_tenant_id_product_id_index" ON "stock_movements" ("tenant_id","product_id");
CREATE INDEX "stock_movements_tenant_id_type_index" ON "stock_movements" ("tenant_id","type");
CREATE INDEX "stock_movements_tenant_id_created_at_index" ON "stock_movements" ("tenant_id","created_at");
CREATE INDEX "stock_movements_batch_id_foreign" ON "stock_movements" ("batch_id");
CREATE INDEX "stock_movements_product_serial_id_foreign" ON "stock_movements" ("product_serial_id");
CREATE INDEX "stock_movements_target_warehouse_id_foreign" ON "stock_movements" ("target_warehouse_id");
CREATE INDEX "stock_movements_stk_mov_tenant_idx" ON "stock_movements" ("tenant_id");
CREATE INDEX "stock_movements_stk_mov_tenant_type_idx" ON "stock_movements" ("tenant_id","type");
CREATE INDEX "stock_movements_stk_mov_tenant_created_idx" ON "stock_movements" ("tenant_id","created_at");
CREATE INDEX "stock_movements_stk_mov_tenant_product" ON "stock_movements" ("tenant_id","product_id");
CREATE INDEX "stock_movements_stk_mov_tenant_warehouse" ON "stock_movements" ("tenant_id","warehouse_id");
CREATE INDEX "stock_movements_del_idx" ON "stock_movements" ("deleted_at");
CREATE INDEX "stock_movements_work_order_id_fk_idx" ON "stock_movements" ("work_order_id");
CREATE INDEX "stock_movements_warehouse_id_fk_idx" ON "stock_movements" ("warehouse_id");
CREATE INDEX "stock_movements_tenant_id_idx" ON "stock_movements" ("tenant_id");
CREATE INDEX "stock_movements_deleted_at_idx" ON "stock_movements" ("deleted_at");

CREATE TABLE "stock_transfer_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "stock_transfer_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "created_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "stock_transfer_items_stock_transfer_id_fk_idx" ON "stock_transfer_items" ("stock_transfer_id");
CREATE INDEX "stock_transfer_items_product_id_fk_idx" ON "stock_transfer_items" ("product_id");

CREATE TABLE "stock_transfers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "from_warehouse_id" integer NOT NULL,
 "to_warehouse_id" integer NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "notes" text,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "to_user_id" integer DEFAULT NULL,
 "accepted_at" datetime NULL DEFAULT NULL,
 "accepted_by" integer DEFAULT NULL,
 "rejected_at" datetime NULL DEFAULT NULL,
 "rejected_by" integer DEFAULT NULL,
 "rejection_reason" varchar(500) DEFAULT NULL,
 "product_id" integer DEFAULT NULL,
 "quantity" numeric DEFAULT NULL
);
CREATE INDEX "stock_transfers_to_user_id_foreign" ON "stock_transfers" ("to_user_id");
CREATE INDEX "stock_transfers_accepted_by_foreign" ON "stock_transfers" ("accepted_by");
CREATE INDEX "stock_transfers_rejected_by_foreign" ON "stock_transfers" ("rejected_by");
CREATE INDEX "stock_transfers_tid_idx" ON "stock_transfers" ("tenant_id");
CREATE INDEX "stock_transfers_from_warehouse_id_fk_idx" ON "stock_transfers" ("from_warehouse_id");
CREATE INDEX "stock_transfers_to_warehouse_id_fk_idx" ON "stock_transfers" ("to_warehouse_id");
CREATE INDEX "stock_transfers_tid_st_idx" ON "stock_transfers" ("tenant_id","status");
CREATE INDEX "stock_transfers_tenant_id_idx" ON "stock_transfers" ("tenant_id");

CREATE TABLE "supplier_contract_payment_frequencies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "supp_ctrt_pay_freq_tid_slug_uq" ON "supplier_contract_payment_frequencies" ("tenant_id","slug");
CREATE INDEX "supplier_contract_payment_frequencies_supp_ctrt_pay_freq_tid_idx" ON "supplier_contract_payment_frequencies" ("tenant_id");
CREATE INDEX "supplier_contract_payment_frequencies_del_idx" ON "supplier_contract_payment_frequencies" ("deleted_at");
CREATE INDEX "supplier_contract_payment_frequencies_deleted_at_idx" ON "supplier_contract_payment_frequencies" ("deleted_at");

CREATE TABLE "supplier_contracts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "supplier_id" integer NOT NULL,
 "description" varchar(255) NOT NULL,
 "start_date" date NOT NULL,
 "end_date" date NOT NULL,
 "value" numeric NOT NULL,
 "payment_frequency" varchar(255) NOT NULL DEFAULT 'monthly',
 "auto_renew" tinyint NOT NULL DEFAULT '0',
 "status" varchar(255) NOT NULL DEFAULT 'active',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "title" varchar(255) DEFAULT NULL,
 "alert_days_before" int DEFAULT NULL
);
CREATE INDEX "supplier_contracts_supplier_id_foreign" ON "supplier_contracts" ("supplier_id");
CREATE INDEX "supplier_contracts_tenant_id_status_index" ON "supplier_contracts" ("tenant_id","status");
CREATE INDEX "supplier_contracts_del_idx" ON "supplier_contracts" ("deleted_at");
CREATE INDEX "supplier_contracts_tenant_id_idx" ON "supplier_contracts" ("tenant_id");
CREATE INDEX "supplier_contracts_deleted_at_idx" ON "supplier_contracts" ("deleted_at");

CREATE TABLE "suppliers" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "type" varchar NOT NULL DEFAULT 'PJ',
 "name" varchar(255) NOT NULL,
 "document" text,
 "document_hash" varchar(64) DEFAULT NULL,
 "trade_name" varchar(255) DEFAULT NULL,
 "email" varchar(255) DEFAULT NULL,
 "phone" varchar(20) DEFAULT NULL,
 "phone2" varchar(20) DEFAULT NULL,
 "address_zip" varchar(10) DEFAULT NULL,
 "address_street" varchar(255) DEFAULT NULL,
 "address_number" varchar(20) DEFAULT NULL,
 "address_complement" varchar(100) DEFAULT NULL,
 "address_neighborhood" varchar(100) DEFAULT NULL,
 "address_city" varchar(100) DEFAULT NULL,
 "address_state" varchar(2) DEFAULT NULL,
 "notes" text,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "suppliers_tenant_id_name_index" ON "suppliers" ("tenant_id","name");
CREATE INDEX "suppliers_sup_deleted_at" ON "suppliers" ("tenant_id","deleted_at");
CREATE INDEX "suppliers_del_idx" ON "suppliers" ("deleted_at");
CREATE INDEX "suppliers_deleted_at_idx" ON "suppliers" ("deleted_at");
CREATE INDEX "suppliers_tenant_document_hash_idx" ON "suppliers" ("tenant_id","document_hash");

CREATE TABLE "support_tickets" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer DEFAULT NULL,
 "source" varchar(30) NOT NULL DEFAULT 'manual',
 "qr_data" text,
 "description" text NOT NULL,
 "priority" varchar(20) NOT NULL DEFAULT 'medium',
 "status" varchar(30) NOT NULL DEFAULT 'open',
 "assigned_to" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "support_tickets_tenant_id_index" ON "support_tickets" ("tenant_id");
CREATE INDEX "support_tickets_customer_id_index" ON "support_tickets" ("customer_id");
CREATE INDEX "support_tickets_tenant_id_idx" ON "support_tickets" ("tenant_id");

CREATE TABLE "survey_responses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "survey_id" integer NOT NULL,
 "respondent_id" integer DEFAULT NULL,
 "answers" text DEFAULT NULL,
 "score" numeric DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "survey_responses_survey_resp_tenant_idx" ON "survey_responses" ("tenant_id","survey_id");
CREATE INDEX "survey_responses_tenant_id_idx" ON "survey_responses" ("tenant_id");

CREATE TABLE "surveys" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "description" text,
 "status" varchar(20) NOT NULL DEFAULT 'draft',
 "created_by" integer DEFAULT NULL,
 "starts_at" datetime NULL DEFAULT NULL,
 "ends_at" datetime NULL DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "surveys_tenant_id_index" ON "surveys" ("tenant_id");
CREATE INDEX "surveys_tenant_id_idx" ON "surveys" ("tenant_id");
CREATE INDEX "surveys_deleted_at_idx" ON "surveys" ("deleted_at");

CREATE TABLE "sync_conflict_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "conflict_type" varchar(50) NOT NULL,
 "client_data" text DEFAULT NULL,
 "server_data" text DEFAULT NULL,
 "resolved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "sync_conflict_logs_tenant_id_index" ON "sync_conflict_logs" ("tenant_id");
CREATE INDEX "sync_conflict_logs_user_id_index" ON "sync_conflict_logs" ("user_id");
CREATE INDEX "sync_conflict_logs_work_order_id_index" ON "sync_conflict_logs" ("work_order_id");
CREATE INDEX "sync_conflict_logs_tenant_id_idx" ON "sync_conflict_logs" ("tenant_id");

CREATE TABLE "sync_queue" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "entity_type" varchar(50) NOT NULL,
 "entity_id" integer DEFAULT NULL,
 "action" varchar(10) NOT NULL,
 "payload" text NOT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "synced_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "sync_queue_tenant_id_index" ON "sync_queue" ("tenant_id");
CREATE INDEX "sync_queue_user_id_index" ON "sync_queue" ("user_id");
CREATE INDEX "sync_queue_entity_id_index" ON "sync_queue" ("entity_id");
CREATE INDEX "sync_queue_tenant_id_idx" ON "sync_queue" ("tenant_id");

CREATE TABLE "sync_queue_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "entity_type" varchar(100) NOT NULL,
 "entity_id" integer DEFAULT NULL,
 "action" varchar(30) NOT NULL,
 "payload" text DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "priority" integer NOT NULL DEFAULT '0',
 "attempts" integer NOT NULL DEFAULT '0',
 "error_message" text,
 "processed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "sync_queue_items_user_id_foreign" ON "sync_queue_items" ("user_id");
CREATE INDEX "sync_queue_items_tenant_id_user_id_status_index" ON "sync_queue_items" ("tenant_id","user_id","status");
CREATE INDEX "sync_queue_items_status_priority_index" ON "sync_queue_items" ("status","priority");
CREATE INDEX "sync_queue_items_tenant_id_idx" ON "sync_queue_items" ("tenant_id");

CREATE TABLE "system_alerts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "alert_type" varchar(255) NOT NULL,
 "severity" varchar(255) NOT NULL DEFAULT 'medium',
 "title" varchar(255) NOT NULL,
 "message" text NOT NULL,
 "alertable_type" varchar(255) DEFAULT NULL,
 "alertable_id" integer DEFAULT NULL,
 "channels_sent" text DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'active',
 "acknowledged_by" integer DEFAULT NULL,
 "acknowledged_at" datetime NULL DEFAULT NULL,
 "resolved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "escalated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "system_alerts_alertable_type_alertable_id_index" ON "system_alerts" ("alertable_type","alertable_id");
CREATE INDEX "system_alerts_acknowledged_by_foreign" ON "system_alerts" ("acknowledged_by");
CREATE INDEX "system_alerts_tenant_id_alert_type_status_index" ON "system_alerts" ("tenant_id","alert_type","status");
CREATE INDEX "system_alerts_tenant_id_idx" ON "system_alerts" ("tenant_id");

CREATE TABLE "system_revisions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "model_type" varchar(255) NOT NULL,
 "model_id" integer NOT NULL,
 "before_payload" text DEFAULT NULL,
 "after_payload" text DEFAULT NULL,
 "action" varchar(255) NOT NULL DEFAULT 'update',
 "user_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "system_revisions_user_id_foreign" ON "system_revisions" ("user_id");
CREATE INDEX "system_revisions_model_type_model_id_index" ON "system_revisions" ("model_type","model_id");
CREATE INDEX "system_revisions_tid_idx" ON "system_revisions" ("tenant_id");
CREATE INDEX "system_revisions_tenant_id_idx" ON "system_revisions" ("tenant_id");

CREATE TABLE "system_settings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "key" varchar(100) NOT NULL,
 "value" text,
 "type" varchar(20) NOT NULL DEFAULT 'string',
 "group" varchar(50) NOT NULL DEFAULT 'general',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "system_settings_tenant_id_key_unique" ON "system_settings" ("tenant_id","key");

CREATE TABLE "tax_calculations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "invoice_id" integer DEFAULT NULL,
 "tax_type" varchar(255) NOT NULL,
 "base_amount" numeric NOT NULL DEFAULT '0.00',
 "rate" numeric NOT NULL DEFAULT '0.0000',
 "tax_amount" numeric NOT NULL DEFAULT '0.00',
 "regime" varchar(255) DEFAULT NULL,
 "calculated_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "tax_calculations_calculated_by_foreign" ON "tax_calculations" ("calculated_by");
CREATE INDEX "tax_calculations_tenant_id_index" ON "tax_calculations" ("tenant_id");
CREATE INDEX "tax_calculations_work_order_id_index" ON "tax_calculations" ("work_order_id");
CREATE INDEX "tax_calculations_invoice_id_index" ON "tax_calculations" ("invoice_id");
CREATE INDEX "tax_calculations_tenant_id_idx" ON "tax_calculations" ("tenant_id");

CREATE TABLE "tech_cash_advances" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "tech_id" integer NOT NULL,
 "amount" numeric NOT NULL,
 "pix_txid" varchar(255) DEFAULT NULL,
 "pix_key" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "reason" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "tech_cash_advances_tech_id_foreign" ON "tech_cash_advances" ("tech_id");
CREATE INDEX "tech_cash_advances_approved_by_foreign" ON "tech_cash_advances" ("approved_by");
CREATE INDEX "tech_cash_advances_tid_idx" ON "tech_cash_advances" ("tenant_id");
CREATE INDEX "tech_cash_advances_tid_st_idx" ON "tech_cash_advances" ("tenant_id","status");
CREATE INDEX "tech_cash_advances_tenant_id_idx" ON "tech_cash_advances" ("tenant_id");

CREATE TABLE "technician_cash_funds" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "balance" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "card_balance" numeric NOT NULL DEFAULT '0.00',
 "credit_limit" numeric DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'active'
);
CREATE UNIQUE INDEX "technician_cash_funds_tenant_id_user_id_unique" ON "technician_cash_funds" ("tenant_id","user_id");
CREATE INDEX "technician_cash_funds_user_id_foreign" ON "technician_cash_funds" ("user_id");
CREATE INDEX "technician_cash_funds_tcf_tenant_user" ON "technician_cash_funds" ("tenant_id","user_id");

CREATE TABLE "technician_cash_transactions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "fund_id" integer NOT NULL,
 "type" varchar NOT NULL,
 "amount" numeric NOT NULL,
 "balance_after" numeric NOT NULL,
 "expense_id" integer DEFAULT NULL,
 "work_order_id" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "description" varchar(255) NOT NULL,
 "transaction_date" date NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "payment_method" varchar(20) NOT NULL DEFAULT 'cash'
);
CREATE INDEX "technician_cash_transactions_expense_id_foreign" ON "technician_cash_transactions" ("expense_id");
CREATE INDEX "technician_cash_transactions_work_order_id_foreign" ON "technician_cash_transactions" ("work_order_id");
CREATE INDEX "technician_cash_transactions_fund_id_transaction_date_index" ON "technician_cash_transactions" ("fund_id","transaction_date");
CREATE INDEX "technician_cash_transactions_tenant_id_index" ON "technician_cash_transactions" ("tenant_id");
CREATE INDEX "technician_cash_transactions_created_by_foreign" ON "technician_cash_transactions" ("created_by");
CREATE INDEX "technician_cash_transactions_tenant_id_idx" ON "technician_cash_transactions" ("tenant_id");

CREATE TABLE "technician_certifications" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "name" varchar(255) NOT NULL,
 "number" varchar(255) DEFAULT NULL,
 "issued_at" date NOT NULL,
 "expires_at" date DEFAULT NULL,
 "issuer" varchar(255) DEFAULT NULL,
 "document_path" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'valid',
 "required_for_service_types" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "technician_certifications_user_id_foreign" ON "technician_certifications" ("user_id");
CREATE INDEX "technician_certifications_tenant_id_user_id_type_index" ON "technician_certifications" ("tenant_id","user_id","type");
CREATE INDEX "technician_certifications_tenant_id_expires_at_index" ON "technician_certifications" ("tenant_id","expires_at");
CREATE INDEX "technician_certifications_deleted_at_idx" ON "technician_certifications" ("deleted_at");

CREATE TABLE "technician_feedbacks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "work_order_id" integer DEFAULT NULL,
 "date" date NOT NULL,
 "type" varchar(30) NOT NULL DEFAULT 'general',
 "message" text NOT NULL,
 "rating" tinyint DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "technician_feedbacks_tenant_id_user_id_date_unique" ON "technician_feedbacks" ("tenant_id","user_id","date");
CREATE INDEX "technician_feedbacks_tenant_id_index" ON "technician_feedbacks" ("tenant_id");
CREATE INDEX "technician_feedbacks_user_id_index" ON "technician_feedbacks" ("user_id");
CREATE INDEX "technician_feedbacks_work_order_id_index" ON "technician_feedbacks" ("work_order_id");

CREATE TABLE "technician_fund_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "amount" numeric NOT NULL,
 "payment_method" varchar(30) DEFAULT NULL,
 "reason" varchar(500) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "approved_by" integer DEFAULT NULL,
 "approved_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "technician_fund_requests_tech_fund_req_tenant_user_idx" ON "technician_fund_requests" ("tenant_id","user_id");
CREATE INDEX "technician_fund_requests_tech_fund_req_tenant_status_idx" ON "technician_fund_requests" ("tenant_id","status");
CREATE INDEX "technician_fund_requests_user_id_foreign" ON "technician_fund_requests" ("user_id");
CREATE INDEX "technician_fund_requests_approved_by_foreign" ON "technician_fund_requests" ("approved_by");
CREATE INDEX "technician_fund_requests_tenant_id_idx" ON "technician_fund_requests" ("tenant_id");

CREATE TABLE "technician_skills" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "skill_name" varchar(255) NOT NULL,
 "category" varchar(255) NOT NULL DEFAULT 'general',
 "proficiency_level" int NOT NULL DEFAULT '1',
 "certification" varchar(255) DEFAULT NULL,
 "certified_at" date DEFAULT NULL,
 "expires_at" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "technician_skills_user_id_foreign" ON "technician_skills" ("user_id");
CREATE INDEX "technician_skills_tenant_id_user_id_index" ON "technician_skills" ("tenant_id","user_id");
CREATE INDEX "technician_skills_tenant_id_skill_name_index" ON "technician_skills" ("tenant_id","skill_name");
CREATE INDEX "technician_skills_tenant_id_idx" ON "technician_skills" ("tenant_id");

CREATE TABLE "telescope_entries" (
 "sequence" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "uuid" char(36) NOT NULL,
 "batch_id" char(36) NOT NULL,
 "family_hash" varchar(255) DEFAULT NULL,
 "should_display_on_index" tinyint NOT NULL DEFAULT '1',
 "type" varchar(20) NOT NULL,
 "content" text NOT NULL,
 "created_at" datetime DEFAULT NULL
);
CREATE UNIQUE INDEX "telescope_entries_uuid_unique" ON "telescope_entries" ("uuid");
CREATE INDEX "telescope_entries_batch_id_index" ON "telescope_entries" ("batch_id");
CREATE INDEX "telescope_entries_family_hash_index" ON "telescope_entries" ("family_hash");
CREATE INDEX "telescope_entries_created_at_index" ON "telescope_entries" ("created_at");
CREATE INDEX "telescope_entries_type_should_display_on_index_index" ON "telescope_entries" ("type","should_display_on_index");

CREATE TABLE "telescope_entries_tags" (
 "entry_uuid" char(36) NOT NULL,
 "tag" varchar(255) NOT NULL,
 PRIMARY KEY ("entry_uuid","tag")
);
CREATE INDEX "telescope_entries_tags_tag_index" ON "telescope_entries_tags" ("tag");

CREATE TABLE "telescope_monitoring" (
 "tag" varchar(255) NOT NULL,
 PRIMARY KEY ("tag")
);

CREATE TABLE "tenant_holidays" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "date" date NOT NULL,
 "name" varchar(100) NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "tenant_holidays_tenant_id_date_unique" ON "tenant_holidays" ("tenant_id","date");
CREATE INDEX "tenant_holidays_tenant_id_index" ON "tenant_holidays" ("tenant_id");

CREATE TABLE "tenant_settings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "key" varchar(255) NOT NULL,
 "value_json" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "value" text
);
CREATE UNIQUE INDEX "tenant_settings_tenant_id_key_unique" ON "tenant_settings" ("tenant_id","key");

CREATE TABLE "tenants" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "name" varchar(255) NOT NULL,
 "document" varchar(20) DEFAULT NULL,
 "email" varchar(255) DEFAULT NULL,
 "phone" varchar(20) DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'active',
 "current_plan_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "inmetro_config" text DEFAULT NULL,
 "trade_name" varchar(255) DEFAULT NULL,
 "website" varchar(255) DEFAULT NULL,
 "state_registration" varchar(30) DEFAULT NULL,
 "city_registration" varchar(30) DEFAULT NULL,
 "address_street" varchar(255) DEFAULT NULL,
 "address_number" varchar(20) DEFAULT NULL,
 "address_complement" varchar(100) DEFAULT NULL,
 "address_neighborhood" varchar(100) DEFAULT NULL,
 "address_city" varchar(100) DEFAULT NULL,
 "address_state" varchar(2) DEFAULT NULL,
 "address_zip" varchar(10) DEFAULT NULL,
 "fiscal_regime" tinyint NOT NULL DEFAULT '1',
 "cnae_code" varchar(20) DEFAULT NULL,
 "fiscal_certificate_path" varchar(255) DEFAULT NULL,
 "fiscal_certificate_password" text,
 "fiscal_certificate_expires_at" date DEFAULT NULL,
 "fiscal_nfse_token" varchar(255) DEFAULT NULL,
 "fiscal_nfse_city" varchar(50) DEFAULT NULL,
 "fiscal_nfe_series" integer NOT NULL DEFAULT '1',
 "fiscal_nfe_next_number" int NOT NULL DEFAULT '1',
 "fiscal_nfse_rps_series" varchar(10) NOT NULL DEFAULT 'RPS',
 "fiscal_nfse_rps_next_number" int NOT NULL DEFAULT '1',
 "fiscal_environment" varchar(20) NOT NULL DEFAULT 'homologation',
 "slug" varchar(255) DEFAULT NULL,
 "is_active" tinyint DEFAULT NULL,
 "signing_key" varchar(64) DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "logo_path" varchar(255) DEFAULT NULL,
 "rep_p_program_name" varchar(100) NOT NULL DEFAULT 'Kalibrium ERP',
 "rep_p_version" varchar(20) NOT NULL DEFAULT '1.0.0',
 "rep_p_developer_name" varchar(100) NOT NULL DEFAULT 'Kalibrium Sistemas',
 "rep_p_developer_cnpj" varchar(14) DEFAULT NULL,
 "timezone" varchar(50) NOT NULL DEFAULT 'America/Sao_Paulo'
);
CREATE INDEX "tenants_current_plan_id_foreign" ON "tenants" ("current_plan_id");
CREATE INDEX "tenants_deleted_at_idx" ON "tenants" ("deleted_at");

CREATE TABLE "ticket_categories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sla_policy_id" integer DEFAULT NULL,
 "default_priority" varchar(255) NOT NULL DEFAULT 'medium',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "ticket_categories_sla_policy_id_foreign" ON "ticket_categories" ("sla_policy_id");
CREATE INDEX "ticket_categories_tenant_id_idx" ON "ticket_categories" ("tenant_id");

CREATE TABLE "time_clock_adjustments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "time_clock_entry_id" integer NOT NULL,
 "requested_by" integer NOT NULL,
 "approved_by" integer DEFAULT NULL,
 "original_clock_in" datetime NULL DEFAULT NULL,
 "original_clock_out" datetime NULL DEFAULT NULL,
 "adjusted_clock_in" datetime NULL DEFAULT NULL,
 "adjusted_clock_out" datetime NULL DEFAULT NULL,
 "reason" text NOT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'pending',
 "rejection_reason" text,
 "decided_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "time_clock_adjustments_time_clock_entry_id_foreign" ON "time_clock_adjustments" ("time_clock_entry_id");
CREATE INDEX "time_clock_adjustments_requested_by_foreign" ON "time_clock_adjustments" ("requested_by");
CREATE INDEX "time_clock_adjustments_approved_by_foreign" ON "time_clock_adjustments" ("approved_by");
CREATE INDEX "time_clock_adjustments_tenant_id_status_index" ON "time_clock_adjustments" ("tenant_id","status");
CREATE INDEX "time_clock_adjustments_tenant_id_idx" ON "time_clock_adjustments" ("tenant_id");

CREATE TABLE "time_clock_audit_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "time_clock_entry_id" integer DEFAULT NULL,
 "time_clock_adjustment_id" integer DEFAULT NULL,
 "action" varchar(255) NOT NULL,
 "performed_by" integer NOT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "user_agent" varchar(255) DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "time_clock_audit_logs_performed_by_foreign" ON "time_clock_audit_logs" ("performed_by");
CREATE INDEX "time_clock_audit_logs_tenant_id_time_clock_entry_id_index" ON "time_clock_audit_logs" ("tenant_id","time_clock_entry_id");
CREATE INDEX "time_clock_audit_logs_tenant_id_action_index" ON "time_clock_audit_logs" ("tenant_id","action");
CREATE INDEX "time_clock_audit_logs_tenant_id_created_at_index" ON "time_clock_audit_logs" ("tenant_id","created_at");
CREATE INDEX "time_clock_audit_logs_time_clock_entry_id_foreign" ON "time_clock_audit_logs" ("time_clock_entry_id");
CREATE INDEX "time_clock_audit_logs_time_clock_adjustment_id_foreign" ON "time_clock_audit_logs" ("time_clock_adjustment_id");
CREATE INDEX "time_clock_audit_logs_tenant_id_idx" ON "time_clock_audit_logs" ("tenant_id");

CREATE TABLE "time_clock_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "clock_in" datetime NOT NULL,
 "clock_out" datetime NULL DEFAULT NULL,
 "latitude_in" numeric DEFAULT NULL,
 "longitude_in" numeric DEFAULT NULL,
 "latitude_out" numeric DEFAULT NULL,
 "longitude_out" numeric DEFAULT NULL,
 "type" varchar(30) NOT NULL DEFAULT 'regular',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "archived_at" datetime NULL DEFAULT NULL,
 "selfie_path" varchar(255) DEFAULT NULL,
 "liveness_score" numeric DEFAULT NULL,
 "liveness_passed" tinyint NOT NULL DEFAULT '0',
 "geofence_location_id" integer DEFAULT NULL,
 "geofence_distance_meters" int DEFAULT NULL,
 "device_info" text DEFAULT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "clock_method" varchar(30) NOT NULL DEFAULT 'selfie',
 "approval_status" varchar(30) NOT NULL DEFAULT 'auto_approved',
 "approved_by" integer DEFAULT NULL,
 "rejection_reason" text,
 "work_order_id" integer DEFAULT NULL,
 "record_hash" varchar(64) NOT NULL DEFAULT '',
 "employee_confirmation_hash" varchar(64) DEFAULT NULL,
 "confirmed_at" datetime NULL DEFAULT NULL,
 "confirmation_method" varchar(20) DEFAULT NULL,
 "previous_hash" varchar(64) DEFAULT NULL,
 "hash_payload" text,
 "nsr" integer DEFAULT NULL,
 "break_start" datetime NULL DEFAULT NULL,
 "break_end" datetime NULL DEFAULT NULL,
 "break_latitude" numeric DEFAULT NULL,
 "break_longitude" numeric DEFAULT NULL,
 "accuracy_in" numeric DEFAULT NULL,
 "accuracy_out" numeric DEFAULT NULL,
 "accuracy_break" numeric DEFAULT NULL,
 "address_in" varchar(500) DEFAULT NULL,
 "address_out" varchar(500) DEFAULT NULL,
 "address_break" varchar(500) DEFAULT NULL,
 "altitude_in" numeric DEFAULT NULL,
 "altitude_out" numeric DEFAULT NULL,
 "speed_in" numeric DEFAULT NULL,
 "location_spoofing_detected" tinyint NOT NULL DEFAULT '0'
);
CREATE UNIQUE INDEX "time_clock_entries_tenant_nsr_unique" ON "time_clock_entries" ("tenant_id","nsr");
CREATE INDEX "time_clock_entries_user_id_foreign" ON "time_clock_entries" ("user_id");
CREATE INDEX "time_clock_entries_approved_by_foreign" ON "time_clock_entries" ("approved_by");
CREATE INDEX "time_clock_entries_work_order_id_foreign" ON "time_clock_entries" ("work_order_id");
CREATE INDEX "time_clock_entries_geofence_location_id_foreign" ON "time_clock_entries" ("geofence_location_id");
CREATE INDEX "time_clock_entries_tenant_id_user_id_clock_in_index" ON "time_clock_entries" ("tenant_id","user_id","clock_in");
CREATE INDEX "time_clock_entries_archived_at_index" ON "time_clock_entries" ("archived_at");

CREATE TABLE "time_entries" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "technician_id" integer DEFAULT NULL,
 "schedule_id" integer DEFAULT NULL,
 "started_at" datetime NOT NULL,
 "ended_at" datetime DEFAULT NULL,
 "duration_minutes" int DEFAULT NULL,
 "type" varchar(20) NOT NULL DEFAULT 'work',
 "description" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "time_entries_schedule_id_foreign" ON "time_entries" ("schedule_id");
CREATE INDEX "time_entries_tenant_id_technician_id_started_at_index" ON "time_entries" ("tenant_id","technician_id","started_at");
CREATE INDEX "time_entries_te_work_order" ON "time_entries" ("work_order_id");
CREATE INDEX "time_entries_technician_id_foreign" ON "time_entries" ("technician_id");
CREATE INDEX "time_entries_te_deleted_at" ON "time_entries" ("deleted_at");
CREATE INDEX "time_entries_del_idx" ON "time_entries" ("deleted_at");
CREATE INDEX "time_entries_tenant_id_idx" ON "time_entries" ("tenant_id");
CREATE INDEX "time_entries_deleted_at_idx" ON "time_entries" ("deleted_at");

CREATE TABLE "toll_transactions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "vehicle_id" integer NOT NULL,
 "toll_name" varchar(255) NOT NULL,
 "amount" numeric NOT NULL,
 "payment_method" varchar(20) NOT NULL,
 "transaction_at" datetime NOT NULL,
 "route" varchar(255) DEFAULT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "toll_transactions_tenant_id_index" ON "toll_transactions" ("tenant_id");
CREATE INDEX "toll_transactions_vehicle_id_index" ON "toll_transactions" ("vehicle_id");
CREATE INDEX "toll_transactions_tenant_id_idx" ON "toll_transactions" ("tenant_id");

CREATE TABLE "tool_calibrations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "tool_inventory_id" integer NOT NULL,
 "calibration_date" date NOT NULL,
 "next_due_date" date NOT NULL,
 "certificate_number" varchar(255) DEFAULT NULL,
 "laboratory" varchar(255) DEFAULT NULL,
 "result" varchar(255) NOT NULL DEFAULT 'approved',
 "certificate_file" varchar(255) DEFAULT NULL,
 "cost" numeric DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "tool_calibrations_tool_inventory_id_next_due_date_index" ON "tool_calibrations" ("tool_inventory_id","next_due_date");
CREATE INDEX "tool_calibrations_tid_idx" ON "tool_calibrations" ("tenant_id");
CREATE INDEX "tool_calibrations_tenant_id_idx" ON "tool_calibrations" ("tenant_id");

CREATE TABLE "tool_checkouts" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "tool_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "checked_out_at" datetime NOT NULL,
 "checked_in_at" datetime NULL DEFAULT NULL,
 "condition_out" varchar(255) NOT NULL DEFAULT 'Bom',
 "condition_in" varchar(255) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "tool_checkouts_tool_id_foreign" ON "tool_checkouts" ("tool_id");
CREATE INDEX "tool_checkouts_user_id_foreign" ON "tool_checkouts" ("user_id");
CREATE INDEX "tool_checkouts_idx_tool_tenant_tool_checked" ON "tool_checkouts" ("tenant_id","tool_id","checked_in_at");
CREATE INDEX "tool_checkouts_del_idx" ON "tool_checkouts" ("deleted_at");
CREATE INDEX "tool_checkouts_tenant_id_idx" ON "tool_checkouts" ("tenant_id");
CREATE INDEX "tool_checkouts_deleted_at_idx" ON "tool_checkouts" ("deleted_at");

CREATE TABLE "tool_inventories" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "serial_number" varchar(50) DEFAULT NULL,
 "category" varchar(50) DEFAULT NULL,
 "assigned_to" integer DEFAULT NULL,
 "fleet_vehicle_id" integer DEFAULT NULL,
 "calibration_due" date DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'available',
 "value" numeric DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "tool_inventories_assigned_to_foreign" ON "tool_inventories" ("assigned_to");
CREATE INDEX "tool_inventories_fleet_vehicle_id_foreign" ON "tool_inventories" ("fleet_vehicle_id");
CREATE INDEX "tool_inventories_tid_idx" ON "tool_inventories" ("tenant_id");
CREATE INDEX "tool_inventories_del_idx" ON "tool_inventories" ("deleted_at");
CREATE INDEX "tool_inventories_tid_st_idx" ON "tool_inventories" ("tenant_id","status");
CREATE INDEX "tool_inventories_tenant_id_idx" ON "tool_inventories" ("tenant_id");
CREATE INDEX "tool_inventories_deleted_at_idx" ON "tool_inventories" ("deleted_at");

CREATE TABLE "traffic_fines" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_vehicle_id" integer NOT NULL,
 "driver_id" integer DEFAULT NULL,
 "fine_date" date NOT NULL,
 "infraction_code" varchar(30) DEFAULT NULL,
 "description" text,
 "amount" numeric NOT NULL,
 "points" int NOT NULL DEFAULT '0',
 "status" varchar(30) NOT NULL DEFAULT 'pending',
 "due_date" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "traffic_fines_fleet_vehicle_id_foreign" ON "traffic_fines" ("fleet_vehicle_id");
CREATE INDEX "traffic_fines_driver_id_foreign" ON "traffic_fines" ("driver_id");
CREATE INDEX "traffic_fines_tid_idx" ON "traffic_fines" ("tenant_id");
CREATE INDEX "traffic_fines_tid_st_idx" ON "traffic_fines" ("tenant_id","status");
CREATE INDEX "traffic_fines_tenant_id_idx" ON "traffic_fines" ("tenant_id");

CREATE TABLE "training_courses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "duration_hours" int NOT NULL,
 "certification_validity_months" int DEFAULT NULL,
 "is_mandatory" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "training_courses_tid_idx" ON "training_courses" ("tenant_id");
CREATE INDEX "training_courses_tenant_id_idx" ON "training_courses" ("tenant_id");

CREATE TABLE "training_enrollments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "course_id" integer NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'enrolled',
 "scheduled_date" date DEFAULT NULL,
 "completed_at" datetime NULL DEFAULT NULL,
 "score" numeric DEFAULT NULL,
 "certification_number" varchar(255) DEFAULT NULL,
 "certification_expires_at" date DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "training_enrollments_user_id_foreign" ON "training_enrollments" ("user_id");
CREATE INDEX "training_enrollments_course_id_foreign" ON "training_enrollments" ("course_id");
CREATE INDEX "training_enrollments_tid_idx" ON "training_enrollments" ("tenant_id");
CREATE INDEX "training_enrollments_tid_st_idx" ON "training_enrollments" ("tenant_id","status");
CREATE INDEX "training_enrollments_tenant_id_idx" ON "training_enrollments" ("tenant_id");

CREATE TABLE "trainings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "title" varchar(255) NOT NULL,
 "institution" varchar(255) NOT NULL,
 "certificate_number" varchar(255) DEFAULT NULL,
 "completion_date" date NOT NULL,
 "expiry_date" date DEFAULT NULL,
 "category" varchar(255) NOT NULL,
 "hours" int NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'completed',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "is_mandatory" tinyint NOT NULL DEFAULT '0',
 "skill_area" varchar(255) DEFAULT NULL,
 "level" varchar NOT NULL DEFAULT 'basic',
 "cost" numeric DEFAULT NULL,
 "instructor" varchar(255) DEFAULT NULL
);
CREATE INDEX "trainings_user_id_foreign" ON "trainings" ("user_id");
CREATE INDEX "trainings_train_tenant" ON "trainings" ("tenant_id");
CREATE INDEX "trainings_tid_idx" ON "trainings" ("tenant_id");
CREATE INDEX "trainings_tid_st_idx" ON "trainings" ("tenant_id","status");
CREATE INDEX "trainings_tenant_id_idx" ON "trainings" ("tenant_id");

CREATE TABLE "travel_advances" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "travel_request_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "amount" numeric NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "paid_at" date DEFAULT NULL,
 "approved_by" integer DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "travel_advances_travel_request_id_foreign" ON "travel_advances" ("travel_request_id");
CREATE INDEX "travel_advances_user_id_foreign" ON "travel_advances" ("user_id");
CREATE INDEX "travel_advances_approved_by_foreign" ON "travel_advances" ("approved_by");
CREATE INDEX "travel_advances_tenant_id_travel_request_id_index" ON "travel_advances" ("tenant_id","travel_request_id");

CREATE TABLE "travel_expense_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "travel_expense_report_id" integer NOT NULL,
 "type" varchar(255) NOT NULL,
 "description" varchar(255) NOT NULL,
 "amount" numeric NOT NULL,
 "expense_date" date NOT NULL,
 "receipt_path" varchar(255) DEFAULT NULL,
 "is_within_policy" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "travel_expense_items_travel_expense_report_id_foreign" ON "travel_expense_items" ("travel_expense_report_id");

CREATE TABLE "travel_expense_reports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "travel_request_id" integer NOT NULL,
 "created_by" integer NOT NULL,
 "total_expenses" numeric NOT NULL DEFAULT '0.00',
 "total_advances" numeric NOT NULL DEFAULT '0.00',
 "balance" numeric NOT NULL DEFAULT '0.00',
 "status" varchar(255) NOT NULL DEFAULT 'draft',
 "approved_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "travel_expense_reports_travel_request_id_foreign" ON "travel_expense_reports" ("travel_request_id");
CREATE INDEX "travel_expense_reports_user_id_foreign" ON "travel_expense_reports" ("created_by");
CREATE INDEX "travel_expense_reports_approved_by_foreign" ON "travel_expense_reports" ("approved_by");
CREATE INDEX "travel_expense_reports_tenant_id_travel_request_id_index" ON "travel_expense_reports" ("tenant_id","travel_request_id");

CREATE TABLE "travel_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "approved_by" integer DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "destination" varchar(255) NOT NULL,
 "purpose" text NOT NULL,
 "departure_date" date NOT NULL,
 "return_date" date NOT NULL,
 "departure_time" time DEFAULT NULL,
 "return_time" time DEFAULT NULL,
 "estimated_days" int NOT NULL,
 "daily_allowance_amount" numeric DEFAULT NULL,
 "total_advance_requested" numeric DEFAULT NULL,
 "requires_vehicle" tinyint NOT NULL DEFAULT '0',
 "fleet_vehicle_id" integer DEFAULT NULL,
 "requires_overnight" tinyint NOT NULL DEFAULT '0',
 "rest_days_after" int NOT NULL DEFAULT '0',
 "overtime_authorized" tinyint NOT NULL DEFAULT '0',
 "work_orders" text DEFAULT NULL,
 "itinerary" text DEFAULT NULL,
 "meal_policy" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "travel_requests_user_id_foreign" ON "travel_requests" ("user_id");
CREATE INDEX "travel_requests_approved_by_foreign" ON "travel_requests" ("approved_by");
CREATE INDEX "travel_requests_fleet_vehicle_id_foreign" ON "travel_requests" ("fleet_vehicle_id");
CREATE INDEX "travel_requests_tenant_id_user_id_status_index" ON "travel_requests" ("tenant_id","user_id","status");
CREATE INDEX "travel_requests_tenant_id_departure_date_index" ON "travel_requests" ("tenant_id","departure_date");
CREATE INDEX "travel_requests_deleted_at_idx" ON "travel_requests" ("deleted_at");

CREATE TABLE "tv_camera_types" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "slug" varchar(255) NOT NULL,
 "description" varchar(255) DEFAULT NULL,
 "color" varchar(20) DEFAULT NULL,
 "icon" varchar(50) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "sort_order" int NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "tv_camera_types_tid_slug_uq" ON "tv_camera_types" ("tenant_id","slug");
CREATE INDEX "tv_camera_types_tid_idx" ON "tv_camera_types" ("tenant_id");
CREATE INDEX "tv_camera_types_del_idx" ON "tv_camera_types" ("deleted_at");
CREATE INDEX "tv_camera_types_deleted_at_idx" ON "tv_camera_types" ("deleted_at");

CREATE TABLE "tv_dashboard_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL DEFAULT 'TV Principal',
 "is_default" tinyint NOT NULL DEFAULT '0',
 "default_mode" varchar(255) NOT NULL DEFAULT 'dashboard',
 "rotation_interval" int NOT NULL DEFAULT '60',
 "camera_grid" varchar(255) NOT NULL DEFAULT '2x2',
 "alert_sound" tinyint NOT NULL DEFAULT '1',
 "kiosk_pin" varchar(255) DEFAULT NULL,
 "technician_offline_minutes" int NOT NULL DEFAULT '15',
 "unattended_call_minutes" int NOT NULL DEFAULT '30',
 "kpi_refresh_seconds" int NOT NULL DEFAULT '30',
 "alert_refresh_seconds" int NOT NULL DEFAULT '60',
 "cache_ttl_seconds" int NOT NULL DEFAULT '30',
 "widgets" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "tv_dashboard_configs_tenant_id_is_default_index" ON "tv_dashboard_configs" ("tenant_id","is_default");
CREATE INDEX "tv_dashboard_configs_tenant_id_idx" ON "tv_dashboard_configs" ("tenant_id");

CREATE TABLE "used_stock_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "work_order_item_id" integer DEFAULT NULL,
 "product_id" integer NOT NULL,
 "technician_warehouse_id" integer NOT NULL,
 "quantity" numeric NOT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'pending_return',
 "reported_by" integer DEFAULT NULL,
 "reported_at" datetime NULL DEFAULT NULL,
 "disposition_type" varchar(30) DEFAULT NULL,
 "disposition_notes" text,
 "confirmed_by" integer DEFAULT NULL,
 "confirmed_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "used_stock_items_work_order_id_foreign" ON "used_stock_items" ("work_order_id");
CREATE INDEX "used_stock_items_work_order_item_id_foreign" ON "used_stock_items" ("work_order_item_id");
CREATE INDEX "used_stock_items_product_id_foreign" ON "used_stock_items" ("product_id");
CREATE INDEX "used_stock_items_technician_warehouse_id_foreign" ON "used_stock_items" ("technician_warehouse_id");
CREATE INDEX "used_stock_items_reported_by_foreign" ON "used_stock_items" ("reported_by");
CREATE INDEX "used_stock_items_confirmed_by_foreign" ON "used_stock_items" ("confirmed_by");
CREATE INDEX "used_stock_items_used_stock_tenant_status_idx" ON "used_stock_items" ("tenant_id","status");
CREATE INDEX "used_stock_items_tenant_id_idx" ON "used_stock_items" ("tenant_id");

CREATE TABLE "user_2fa" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "user_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "secret" text NOT NULL,
 "method" varchar(20) NOT NULL DEFAULT 'email',
 "is_enabled" tinyint NOT NULL DEFAULT '0',
 "verified_at" datetime NULL DEFAULT NULL,
 "backup_codes" text,
 "created_at" datetime NOT NULL
);
CREATE UNIQUE INDEX "user_2fa_user_id_unique" ON "user_2fa" ("user_id");
CREATE INDEX "user_2fa_tenant_id_index" ON "user_2fa" ("tenant_id");
CREATE INDEX "user_2fa_tenant_id_idx" ON "user_2fa" ("tenant_id");

CREATE TABLE "user_competencies" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "equipment_id" integer DEFAULT NULL,
 "supervisor_id" integer DEFAULT NULL,
 "method_name" varchar(255) DEFAULT NULL,
 "category" varchar(255) DEFAULT NULL,
 "status" varchar NOT NULL DEFAULT 'active',
 "issued_at" date NOT NULL,
 "expires_at" date DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "user_competencies_user_id_foreign" ON "user_competencies" ("user_id");
CREATE INDEX "user_competencies_equipment_id_foreign" ON "user_competencies" ("equipment_id");
CREATE INDEX "user_competencies_supervisor_id_foreign" ON "user_competencies" ("supervisor_id");
CREATE INDEX "user_competencies_tenant_id_user_id_index" ON "user_competencies" ("tenant_id","user_id");
CREATE INDEX "user_competencies_tenant_id_status_index" ON "user_competencies" ("tenant_id","status");
CREATE INDEX "user_competencies_tenant_id_idx" ON "user_competencies" ("tenant_id");

CREATE TABLE "user_favorites" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "favoritable_type" varchar(255) NOT NULL,
 "favoritable_id" integer NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "user_favorites_unique" ON "user_favorites" ("user_id","favoritable_type","favoritable_id");
CREATE INDEX "user_favorites_favoritable_type_favoritable_id_index" ON "user_favorites" ("favoritable_type","favoritable_id");
CREATE INDEX "user_favorites_user_id_index" ON "user_favorites" ("user_id");
CREATE INDEX "user_favorites_tenant_id_idx" ON "user_favorites" ("tenant_id");

CREATE TABLE "user_preferences" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "dark_mode" tinyint NOT NULL DEFAULT '0',
 "language" varchar(5) NOT NULL DEFAULT 'pt_BR',
 "notifications" tinyint NOT NULL DEFAULT '1',
 "data_saver" tinyint NOT NULL DEFAULT '0',
 "offline_sync" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "user_preferences_user_id_unique" ON "user_preferences" ("user_id");
CREATE INDEX "user_preferences_tenant_id_idx" ON "user_preferences" ("tenant_id");

CREATE TABLE "user_sessions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "token_id" varchar(255) DEFAULT NULL,
 "ip_address" varchar(45) DEFAULT NULL,
 "user_agent" text,
 "last_activity" datetime NOT NULL,
 "created_at" datetime NOT NULL
);
CREATE INDEX "user_sessions_user_id_index" ON "user_sessions" ("user_id");
CREATE INDEX "user_sessions_token_id_index" ON "user_sessions" ("token_id");
CREATE INDEX "user_sessions_tenant_id_idx" ON "user_sessions" ("tenant_id");

CREATE TABLE "user_skills" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "skill_id" integer NOT NULL,
 "current_level" int NOT NULL DEFAULT '1',
 "assessed_at" date DEFAULT NULL,
 "assessed_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "user_skills_user_id_foreign" ON "user_skills" ("user_id");
CREATE INDEX "user_skills_skill_id_foreign" ON "user_skills" ("skill_id");
CREATE INDEX "user_skills_assessed_by_foreign" ON "user_skills" ("assessed_by");
CREATE INDEX "user_skills_tenant_id_index" ON "user_skills" ("tenant_id");
CREATE INDEX "user_skills_tenant_id_idx" ON "user_skills" ("tenant_id");

CREATE TABLE "user_tenants" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "user_id" integer NOT NULL,
 "tenant_id" integer NOT NULL,
 "is_default" tinyint NOT NULL DEFAULT '0',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "user_tenants_user_id_tenant_id_unique" ON "user_tenants" ("user_id","tenant_id");
CREATE INDEX "user_tenants_tid_idx" ON "user_tenants" ("tenant_id");
CREATE INDEX "user_tenants_tenant_id_idx" ON "user_tenants" ("tenant_id");

CREATE TABLE "users" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "name" varchar(255) NOT NULL,
 "email" varchar(255) NOT NULL,
 "email_verified_at" datetime NULL DEFAULT NULL,
 "password" varchar(255) NOT NULL,
 "remember_token" varchar(100) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "phone" varchar(20) DEFAULT NULL,
 "pis_number" varchar(11) DEFAULT NULL,
 "cpf" text,
 "cpf_hash" varchar(64) DEFAULT NULL,
 "ctps_number" varchar(20) DEFAULT NULL,
 "ctps_series" varchar(10) DEFAULT NULL,
 "admission_date" date DEFAULT NULL,
 "termination_date" date DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "tenant_id" integer DEFAULT NULL,
 "current_tenant_id" integer DEFAULT NULL,
 "last_login_at" datetime NULL DEFAULT NULL,
 "branch_id" integer DEFAULT NULL,
 "location_lat" numeric DEFAULT NULL,
 "location_lng" numeric DEFAULT NULL,
 "location_updated_at" datetime NULL DEFAULT NULL,
 "status" varchar NOT NULL DEFAULT 'offline',
 "department_id" integer DEFAULT NULL,
 "position_id" integer DEFAULT NULL,
 "manager_id" integer DEFAULT NULL,
 "hire_date" date DEFAULT NULL,
 "salary" numeric DEFAULT NULL,
 "salary_type" varchar(20) DEFAULT 'monthly',
 "work_shift" varchar(50) DEFAULT NULL,
 "journey_rule_id" integer DEFAULT NULL,
 "cbo_code" varchar(10) DEFAULT NULL,
 "birth_date" date DEFAULT NULL,
 "gender" varchar(10) DEFAULT NULL,
 "marital_status" varchar(20) DEFAULT NULL,
 "education_level" varchar(30) DEFAULT NULL,
 "nationality" varchar(50) DEFAULT 'brasileira',
 "rg_number" varchar(20) DEFAULT NULL,
 "rg_issuer" varchar(20) DEFAULT NULL,
 "voter_title" varchar(20) DEFAULT NULL,
 "military_cert" varchar(20) DEFAULT NULL,
 "bank_code" varchar(10) DEFAULT NULL,
 "bank_agency" varchar(10) DEFAULT NULL,
 "bank_account" varchar(20) DEFAULT NULL,
 "bank_account_type" varchar(20) DEFAULT 'checking',
 "dependents_count" int NOT NULL DEFAULT '0',
 "hour_bank_minutes" int NOT NULL DEFAULT '0',
 "vacation_days_remaining" int NOT NULL DEFAULT '30',
 "denied_permissions" text DEFAULT NULL,
 "google_calendar_token" text,
 "google_calendar_refresh_token" text,
 "google_calendar_email" varchar(255) DEFAULT NULL,
 "google_calendar_synced_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email");
CREATE INDEX "users_manager_id_foreign" ON "users" ("manager_id");
CREATE INDEX "users_tid_idx" ON "users" ("tenant_id");
CREATE INDEX "users_current_tenant_id_fk_idx" ON "users" ("current_tenant_id");
CREATE INDEX "users_branch_id_fk_idx" ON "users" ("branch_id");
CREATE INDEX "users_department_id_fk_idx" ON "users" ("department_id");
CREATE INDEX "users_position_id_fk_idx" ON "users" ("position_id");
CREATE INDEX "users_journey_rule_id_foreign" ON "users" ("journey_rule_id");
CREATE INDEX "users_deleted_at_idx" ON "users" ("deleted_at");
CREATE INDEX "users_tenant_cpf_hash_idx" ON "users" ("tenant_id","cpf_hash");

CREATE TABLE "vacation_balances" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "acquisition_start" date NOT NULL,
 "acquisition_end" date NOT NULL,
 "total_days" int NOT NULL DEFAULT '30',
 "taken_days" int NOT NULL DEFAULT '0',
 "sold_days" int NOT NULL DEFAULT '0',
 "remaining_days" int GENERATED ALWAYS AS ((("total_days" - "taken_days") - "sold_days")) STORED,
 "deadline" date NOT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'accruing',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "vacation_balances_user_id_foreign" ON "vacation_balances" ("user_id");
CREATE INDEX "vacation_balances_tid_idx" ON "vacation_balances" ("tenant_id");
CREATE INDEX "vacation_balances_tenant_id_idx" ON "vacation_balances" ("tenant_id");

CREATE TABLE "vehicle_accidents" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_vehicle_id" integer DEFAULT NULL,
 "driver_id" integer DEFAULT NULL,
 "occurrence_date" date DEFAULT NULL,
 "location" varchar(255) DEFAULT NULL,
 "description" text,
 "third_party_involved" tinyint NOT NULL DEFAULT '0',
 "third_party_info" text,
 "police_report_number" varchar(255) DEFAULT NULL,
 "photos" text DEFAULT NULL,
 "estimated_cost" numeric DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'investigating'
);
CREATE INDEX "vehicle_accidents_tenant_status_idx" ON "vehicle_accidents" ("tenant_id","status");
CREATE INDEX "vehicle_accidents_vehicle_date_idx" ON "vehicle_accidents" ("fleet_vehicle_id","occurrence_date");
CREATE INDEX "vehicle_accidents_driver_id_index" ON "vehicle_accidents" ("driver_id");
CREATE INDEX "vehicle_accidents_tenant_id_idx" ON "vehicle_accidents" ("tenant_id");

CREATE TABLE "vehicle_gps_positions" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "vehicle_id" integer NOT NULL,
 "latitude" numeric NOT NULL,
 "longitude" numeric NOT NULL,
 "speed_kmh" numeric DEFAULT NULL,
 "heading" numeric DEFAULT NULL,
 "recorded_at" datetime NOT NULL
);
CREATE INDEX "vehicle_gps_positions_tenant_id_index" ON "vehicle_gps_positions" ("tenant_id");
CREATE INDEX "vehicle_gps_positions_vehicle_id_index" ON "vehicle_gps_positions" ("vehicle_id");
CREATE INDEX "vehicle_gps_positions_recorded_at_index" ON "vehicle_gps_positions" ("recorded_at");
CREATE INDEX "vehicle_gps_positions_tenant_id_idx" ON "vehicle_gps_positions" ("tenant_id");

CREATE TABLE "vehicle_inspections" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_vehicle_id" integer NOT NULL,
 "inspector_id" integer NOT NULL,
 "inspection_date" date NOT NULL,
 "odometer_km" int NOT NULL,
 "checklist_data" text DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'ok',
 "observations" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "vehicle_inspections_fleet_vehicle_id_foreign" ON "vehicle_inspections" ("fleet_vehicle_id");
CREATE INDEX "vehicle_inspections_inspector_id_foreign" ON "vehicle_inspections" ("inspector_id");
CREATE INDEX "vehicle_inspections_tid_idx" ON "vehicle_inspections" ("tenant_id");
CREATE INDEX "vehicle_inspections_tid_st_idx" ON "vehicle_inspections" ("tenant_id","status");
CREATE INDEX "vehicle_inspections_tenant_id_idx" ON "vehicle_inspections" ("tenant_id");

CREATE TABLE "vehicle_insurances" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_vehicle_id" integer NOT NULL,
 "insurer" varchar(150) NOT NULL,
 "policy_number" varchar(80) DEFAULT NULL,
 "coverage_type" varchar(50) NOT NULL DEFAULT 'comprehensive',
 "premium_value" numeric NOT NULL DEFAULT '0.00',
 "deductible_value" numeric NOT NULL DEFAULT '0.00',
 "start_date" date NOT NULL,
 "end_date" date NOT NULL,
 "broker_name" varchar(150) DEFAULT NULL,
 "broker_phone" varchar(30) DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'active',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "vehicle_insurances_fleet_vehicle_id_index" ON "vehicle_insurances" ("fleet_vehicle_id");
CREATE INDEX "vehicle_insurances_tenant_id_index" ON "vehicle_insurances" ("tenant_id");
CREATE INDEX "vehicle_insurances_del_idx" ON "vehicle_insurances" ("deleted_at");
CREATE INDEX "vehicle_insurances_tenant_id_idx" ON "vehicle_insurances" ("tenant_id");
CREATE INDEX "vehicle_insurances_deleted_at_idx" ON "vehicle_insurances" ("deleted_at");

CREATE TABLE "vehicle_pool_requests" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "fleet_vehicle_id" integer DEFAULT NULL,
 "requested_start" datetime NULL DEFAULT NULL,
 "requested_end" datetime NULL DEFAULT NULL,
 "actual_start" datetime NULL DEFAULT NULL,
 "actual_end" datetime NULL DEFAULT NULL,
 "purpose" text,
 "status" varchar(30) NOT NULL DEFAULT 'pending'
);
CREATE INDEX "vehicle_pool_requests_vehicle_pool_tenant_status_idx" ON "vehicle_pool_requests" ("tenant_id","status");
CREATE INDEX "vehicle_pool_requests_vehicle_pool_user_idx" ON "vehicle_pool_requests" ("user_id");
CREATE INDEX "vehicle_pool_requests_fleet_vehicle_id_index" ON "vehicle_pool_requests" ("fleet_vehicle_id");
CREATE INDEX "vehicle_pool_requests_tenant_id_idx" ON "vehicle_pool_requests" ("tenant_id");

CREATE TABLE "vehicle_tires" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "fleet_vehicle_id" integer NOT NULL,
 "serial_number" varchar(255) DEFAULT NULL,
 "brand" varchar(255) DEFAULT NULL,
 "model" varchar(255) DEFAULT NULL,
 "position" varchar(255) NOT NULL,
 "tread_depth" numeric DEFAULT NULL,
 "retread_count" int NOT NULL DEFAULT '0',
 "installed_at" date DEFAULT NULL,
 "installed_km" int DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'active',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "vehicle_tires_fleet_vehicle_id_index" ON "vehicle_tires" ("fleet_vehicle_id");
CREATE INDEX "vehicle_tires_tenant_id_index" ON "vehicle_tires" ("tenant_id");
CREATE INDEX "vehicle_tires_tenant_status_idx" ON "vehicle_tires" ("tenant_id","status");
CREATE INDEX "vehicle_tires_vehicle_idx" ON "vehicle_tires" ("fleet_vehicle_id");
CREATE INDEX "vehicle_tires_del_idx" ON "vehicle_tires" ("deleted_at");
CREATE INDEX "vehicle_tires_tenant_id_idx" ON "vehicle_tires" ("tenant_id");
CREATE INDEX "vehicle_tires_deleted_at_idx" ON "vehicle_tires" ("deleted_at");

CREATE TABLE "virtual_cards" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "card_id_api" varchar(255) DEFAULT NULL,
 "os_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "limit_amount" numeric NOT NULL DEFAULT '0.00',
 "spent_amount" numeric NOT NULL DEFAULT '0.00',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "expires_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "virtual_cards_os_id_foreign" ON "virtual_cards" ("os_id");
CREATE INDEX "virtual_cards_user_id_foreign" ON "virtual_cards" ("user_id");
CREATE INDEX "virtual_cards_tid_idx" ON "virtual_cards" ("tenant_id");
CREATE INDEX "virtual_cards_tenant_id_idx" ON "virtual_cards" ("tenant_id");

CREATE TABLE "visit_checkins" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "activity_id" integer DEFAULT NULL,
 "checkin_at" datetime NOT NULL,
 "checkin_lat" numeric DEFAULT NULL,
 "checkin_lng" numeric DEFAULT NULL,
 "checkin_address" varchar(255) DEFAULT NULL,
 "checkin_photo" varchar(255) DEFAULT NULL,
 "checkout_at" datetime NULL DEFAULT NULL,
 "checkout_lat" numeric DEFAULT NULL,
 "checkout_lng" numeric DEFAULT NULL,
 "checkout_photo" varchar(255) DEFAULT NULL,
 "duration_minutes" int DEFAULT NULL,
 "distance_from_client_meters" numeric DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'checked_in',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "visit_checkins_customer_id_foreign" ON "visit_checkins" ("customer_id");
CREATE INDEX "visit_checkins_user_id_foreign" ON "visit_checkins" ("user_id");
CREATE INDEX "visit_checkins_activity_id_foreign" ON "visit_checkins" ("activity_id");
CREATE INDEX "visit_checkins_visit_ck_tenant_user_idx" ON "visit_checkins" ("tenant_id","user_id","checkin_at");
CREATE INDEX "visit_checkins_visit_ck_tenant_cust_idx" ON "visit_checkins" ("tenant_id","customer_id");
CREATE INDEX "visit_checkins_tenant_id_idx" ON "visit_checkins" ("tenant_id");

CREATE TABLE "visit_reports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "checkin_id" integer DEFAULT NULL,
 "deal_id" integer DEFAULT NULL,
 "visit_date" date NOT NULL,
 "visit_type" varchar(255) NOT NULL DEFAULT 'in_person',
 "contact_name" varchar(255) DEFAULT NULL,
 "contact_role" varchar(255) DEFAULT NULL,
 "summary" text NOT NULL,
 "decisions" text,
 "next_steps" text,
 "overall_sentiment" varchar(255) DEFAULT NULL,
 "topics" text DEFAULT NULL,
 "attachments" text DEFAULT NULL,
 "follow_up_scheduled" tinyint NOT NULL DEFAULT '0',
 "next_contact_at" datetime NULL DEFAULT NULL,
 "next_contact_type" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "visit_reports_customer_id_foreign" ON "visit_reports" ("customer_id");
CREATE INDEX "visit_reports_user_id_foreign" ON "visit_reports" ("user_id");
CREATE INDEX "visit_reports_checkin_id_foreign" ON "visit_reports" ("checkin_id");
CREATE INDEX "visit_reports_deal_id_foreign" ON "visit_reports" ("deal_id");
CREATE INDEX "visit_reports_vr_tenant_cust_idx" ON "visit_reports" ("tenant_id","customer_id");
CREATE INDEX "visit_reports_vr_tenant_user_date_idx" ON "visit_reports" ("tenant_id","user_id","visit_date");
CREATE INDEX "visit_reports_tenant_id_idx" ON "visit_reports" ("tenant_id");

CREATE TABLE "visit_route_stops" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "visit_route_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "checkin_id" integer DEFAULT NULL,
 "stop_order" int NOT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "estimated_duration_minutes" int DEFAULT NULL,
 "objective" varchar(255) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "visit_route_stops_customer_id_foreign" ON "visit_route_stops" ("customer_id");
CREATE INDEX "visit_route_stops_checkin_id_foreign" ON "visit_route_stops" ("checkin_id");
CREATE INDEX "visit_route_stops_vrs_route_order_idx" ON "visit_route_stops" ("visit_route_id","stop_order");

CREATE TABLE "visit_routes" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "route_date" date NOT NULL,
 "name" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'planned',
 "total_stops" int NOT NULL DEFAULT '0',
 "completed_stops" int NOT NULL DEFAULT '0',
 "total_distance_km" numeric DEFAULT NULL,
 "estimated_duration_minutes" int DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "visit_routes_user_id_foreign" ON "visit_routes" ("user_id");
CREATE INDEX "visit_routes_visit_rt_tenant_user_dt_idx" ON "visit_routes" ("tenant_id","user_id","route_date");
CREATE INDEX "visit_routes_tenant_id_idx" ON "visit_routes" ("tenant_id");

CREATE TABLE "visit_surveys" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "checkin_id" integer DEFAULT NULL,
 "user_id" integer NOT NULL,
 "token" varchar(64) NOT NULL,
 "rating" int DEFAULT NULL,
 "comment" text,
 "status" varchar(255) NOT NULL DEFAULT 'pending',
 "sent_at" datetime NULL DEFAULT NULL,
 "answered_at" datetime NULL DEFAULT NULL,
 "expires_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "visit_surveys_token_unique" ON "visit_surveys" ("token");
CREATE INDEX "visit_surveys_customer_id_foreign" ON "visit_surveys" ("customer_id");
CREATE INDEX "visit_surveys_checkin_id_foreign" ON "visit_surveys" ("checkin_id");
CREATE INDEX "visit_surveys_user_id_foreign" ON "visit_surveys" ("user_id");
CREATE INDEX "visit_surveys_vs_tenant_status_idx" ON "visit_surveys" ("tenant_id","status");
CREATE INDEX "visit_surveys_tenant_id_idx" ON "visit_surveys" ("tenant_id");

CREATE TABLE "voice_reports" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "transcription" text NOT NULL,
 "duration_seconds" int DEFAULT NULL,
 "language" varchar(5) NOT NULL DEFAULT 'pt_BR',
 "created_at" datetime NOT NULL
);
CREATE INDEX "voice_reports_tenant_id_index" ON "voice_reports" ("tenant_id");
CREATE INDEX "voice_reports_user_id_index" ON "voice_reports" ("user_id");
CREATE INDEX "voice_reports_work_order_id_index" ON "voice_reports" ("work_order_id");
CREATE INDEX "voice_reports_tenant_id_idx" ON "voice_reports" ("tenant_id");

CREATE TABLE "vulnerability_scans" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "scan_type" varchar(20) NOT NULL DEFAULT 'full',
 "status" varchar(20) NOT NULL DEFAULT 'running',
 "findings" text DEFAULT NULL,
 "critical_count" int NOT NULL DEFAULT '0',
 "warning_count" int NOT NULL DEFAULT '0',
 "requested_by" integer DEFAULT NULL,
 "scanned_at" datetime NOT NULL,
 "completed_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "vulnerability_scans_tenant_id_index" ON "vulnerability_scans" ("tenant_id");
CREATE INDEX "vulnerability_scans_tenant_id_idx" ON "vulnerability_scans" ("tenant_id");

CREATE TABLE "warehouse_stocks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "warehouse_id" integer NOT NULL,
 "product_id" integer NOT NULL,
 "batch_id" integer DEFAULT NULL,
 "quantity" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "ws_unique" ON "warehouse_stocks" ("warehouse_id","product_id","batch_id");
CREATE INDEX "warehouse_stocks_batch_id_foreign" ON "warehouse_stocks" ("batch_id");
CREATE INDEX "warehouse_stocks_ws_product" ON "warehouse_stocks" ("product_id");
CREATE INDEX "warehouse_stocks_tenant_id_idx" ON "warehouse_stocks" ("tenant_id");

CREATE TABLE "warehouses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "code" varchar(255) DEFAULT NULL,
 "type" varchar(20) NOT NULL DEFAULT 'fixed',
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "user_id" integer DEFAULT NULL,
 "vehicle_id" integer DEFAULT NULL,
 "is_main" tinyint DEFAULT NULL
);
CREATE INDEX "warehouses_user_id_foreign" ON "warehouses" ("user_id");
CREATE INDEX "warehouses_vehicle_id_foreign" ON "warehouses" ("vehicle_id");
CREATE INDEX "warehouses_wh_tenant" ON "warehouses" ("tenant_id");
CREATE INDEX "warehouses_wh_deleted_at" ON "warehouses" ("tenant_id","deleted_at");
CREATE INDEX "warehouses_tid_idx" ON "warehouses" ("tenant_id");
CREATE INDEX "warehouses_del_idx" ON "warehouses" ("deleted_at");
CREATE INDEX "warehouses_tenant_id_idx" ON "warehouses" ("tenant_id");
CREATE INDEX "warehouses_deleted_at_idx" ON "warehouses" ("deleted_at");

CREATE TABLE "warranty_tracking" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "equipment_id" integer DEFAULT NULL,
 "product_id" integer DEFAULT NULL,
 "work_order_item_id" integer DEFAULT NULL,
 "warranty_start_at" date NOT NULL,
 "warranty_end_at" date NOT NULL,
 "warranty_type" varchar(30) NOT NULL DEFAULT 'part',
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "warranty_tracking_work_order_id_foreign" ON "warranty_tracking" ("work_order_id");
CREATE INDEX "warranty_tracking_product_id_foreign" ON "warranty_tracking" ("product_id");
CREATE INDEX "warranty_tracking_work_order_item_id_foreign" ON "warranty_tracking" ("work_order_item_id");
CREATE INDEX "warranty_tracking_warranty_tenant_end_idx" ON "warranty_tracking" ("tenant_id","warranty_end_at");
CREATE INDEX "warranty_tracking_warranty_customer_end_idx" ON "warranty_tracking" ("customer_id","warranty_end_at");
CREATE INDEX "warranty_tracking_warranty_equip_end_idx" ON "warranty_tracking" ("equipment_id","warranty_end_at");
CREATE INDEX "warranty_tracking_tenant_id_idx" ON "warranty_tracking" ("tenant_id");

CREATE TABLE "watermark_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "enabled" tinyint NOT NULL DEFAULT '0',
 "text" varchar(100) NOT NULL DEFAULT 'CONFIDENCIAL',
 "opacity" int NOT NULL DEFAULT '30',
 "position" varchar(20) NOT NULL DEFAULT 'diagonal',
 "include_user_info" tinyint NOT NULL DEFAULT '0',
 "include_timestamp" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "watermark_configs_tenant_id_unique" ON "watermark_configs" ("tenant_id");

CREATE TABLE "webhook_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "url" varchar(500) NOT NULL,
 "events" text NOT NULL,
 "secret" varchar(255) NOT NULL,
 "headers" text DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "last_triggered_at" datetime NULL DEFAULT NULL,
 "failure_count" int NOT NULL DEFAULT '0'
);
CREATE INDEX "webhook_configs_tid_idx" ON "webhook_configs" ("tenant_id");
CREATE INDEX "webhook_configs_tenant_id_idx" ON "webhook_configs" ("tenant_id");

CREATE TABLE "webhook_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer DEFAULT NULL,
 "webhook_id" integer NOT NULL,
 "event" varchar(100) NOT NULL,
 "payload" text DEFAULT NULL,
 "response_status" int DEFAULT NULL,
 "response_body" text,
 "duration_ms" int DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "webhook_logs_webhook_id_foreign" ON "webhook_logs" ("webhook_id");
CREATE INDEX "webhook_logs_tenant_id_idx" ON "webhook_logs" ("tenant_id");

CREATE TABLE "webhooks" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL DEFAULT 'Webhook',
 "url" varchar(500) NOT NULL,
 "event" varchar(50) NOT NULL,
 "events" text DEFAULT NULL,
 "secret" varchar(64) NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "failure_count" int NOT NULL DEFAULT '0',
 "last_triggered_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "webhooks_tenant_id_index" ON "webhooks" ("tenant_id");
CREATE INDEX "webhooks_event_index" ON "webhooks" ("event");
CREATE INDEX "webhooks_tenant_id_idx" ON "webhooks" ("tenant_id");
CREATE INDEX "webhooks_deleted_at_idx" ON "webhooks" ("deleted_at");

CREATE TABLE "weight_assignments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "standard_weight_id" integer NOT NULL,
 "assigned_to_user_id" integer DEFAULT NULL,
 "assigned_to_vehicle_id" integer DEFAULT NULL,
 "assignment_type" varchar(255) NOT NULL DEFAULT 'field',
 "assigned_at" datetime NOT NULL,
 "returned_at" datetime NULL DEFAULT NULL,
 "assigned_by" integer NOT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "weight_assignments_standard_weight_id_foreign" ON "weight_assignments" ("standard_weight_id");
CREATE INDEX "weight_assignments_assigned_to_user_id_foreign" ON "weight_assignments" ("assigned_to_user_id");
CREATE INDEX "weight_assignments_assigned_to_vehicle_id_foreign" ON "weight_assignments" ("assigned_to_vehicle_id");
CREATE INDEX "weight_assignments_assigned_by_foreign" ON "weight_assignments" ("assigned_by");
CREATE INDEX "weight_assignments_tenant_id_standard_weight_id_index" ON "weight_assignments" ("tenant_id","standard_weight_id");
CREATE INDEX "weight_assignments_tenant_id_idx" ON "weight_assignments" ("tenant_id");

CREATE TABLE "whatsapp_configs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "provider" varchar(255) NOT NULL DEFAULT 'evolution',
 "api_url" varchar(255) NOT NULL,
 "api_key" varchar(255) NOT NULL,
 "instance_name" varchar(255) DEFAULT NULL,
 "phone_number" varchar(255) DEFAULT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "settings" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "whatsapp_configs_tid_idx" ON "whatsapp_configs" ("tenant_id");
CREATE INDEX "whatsapp_configs_tenant_id_idx" ON "whatsapp_configs" ("tenant_id");

CREATE TABLE "whatsapp_messages" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "direction" varchar(10) NOT NULL DEFAULT 'outbound',
 "phone_to" varchar(50) DEFAULT NULL,
 "phone_from" varchar(50) DEFAULT NULL,
 "customer_id" integer DEFAULT NULL,
 "phone" varchar(255) DEFAULT NULL,
 "message" text NOT NULL,
 "message_type" varchar(30) NOT NULL DEFAULT 'text',
 "template_name" varchar(255) DEFAULT NULL,
 "template_params" text DEFAULT NULL,
 "template" varchar(255) DEFAULT NULL,
 "status" varchar(255) NOT NULL DEFAULT 'queued',
 "external_id" varchar(255) DEFAULT NULL,
 "error_message" text,
 "related_type" varchar(255) DEFAULT NULL,
 "related_id" integer DEFAULT NULL,
 "sent_at" datetime NULL DEFAULT NULL,
 "delivered_at" datetime NULL DEFAULT NULL,
 "read_at" datetime NULL DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "whatsapp_messages_tenant_id_index" ON "whatsapp_messages" ("tenant_id");
CREATE INDEX "whatsapp_messages_customer_id_foreign" ON "whatsapp_messages" ("customer_id");
CREATE INDEX "whatsapp_messages_tenant_id_idx" ON "whatsapp_messages" ("tenant_id");

CREATE TABLE "work_order_approvals" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "work_order_id" integer NOT NULL,
 "approver_id" integer DEFAULT NULL,
 "requested_by" integer DEFAULT NULL,
 "status" varchar(20) NOT NULL DEFAULT 'pending',
 "notes" text,
 "responded_at" datetime NULL DEFAULT NULL,
 "response_notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_approvals_approver_id_foreign" ON "work_order_approvals" ("approver_id");
CREATE INDEX "work_order_approvals_requested_by_foreign" ON "work_order_approvals" ("requested_by");
CREATE INDEX "work_order_approvals_wo_approvals_wo_status_idx" ON "work_order_approvals" ("work_order_id","status");

CREATE TABLE "work_order_attachments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "work_order_id" integer NOT NULL,
 "uploaded_by" integer DEFAULT NULL,
 "file_name" varchar(255) NOT NULL,
 "file_path" varchar(255) NOT NULL,
 "file_type" varchar(50) DEFAULT NULL,
 "file_size" int DEFAULT NULL,
 "description" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL,
 "category" varchar(30) NOT NULL DEFAULT 'general'
);
CREATE INDEX "work_order_attachments_uploaded_by_foreign" ON "work_order_attachments" ("uploaded_by");
CREATE INDEX "work_order_attachments_work_order_id_index" ON "work_order_attachments" ("work_order_id");
CREATE INDEX "work_order_attachments_tenant_id_index" ON "work_order_attachments" ("tenant_id");
CREATE INDEX "work_order_attachments_woa_work_order" ON "work_order_attachments" ("work_order_id");
CREATE INDEX "work_order_attachments_tenant_id_idx" ON "work_order_attachments" ("tenant_id");

CREATE TABLE "work_order_chats" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "message" text NOT NULL,
 "type" varchar(255) NOT NULL DEFAULT 'text',
 "file_path" varchar(255) DEFAULT NULL,
 "read_at" datetime NULL DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_chats_user_id_foreign" ON "work_order_chats" ("user_id");
CREATE INDEX "work_order_chats_woc_work_order" ON "work_order_chats" ("work_order_id");
CREATE INDEX "work_order_chats_tid_idx" ON "work_order_chats" ("tenant_id");
CREATE INDEX "work_order_chats_tenant_id_idx" ON "work_order_chats" ("tenant_id");

CREATE TABLE "work_order_checklist_responses" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "work_order_id" integer NOT NULL,
 "checklist_item_id" integer NOT NULL,
 "value" text,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "work_order_checklist_responses_work_order_id_foreign" ON "work_order_checklist_responses" ("work_order_id");
CREATE INDEX "work_order_checklist_responses_checklist_item_id_foreign" ON "work_order_checklist_responses" ("checklist_item_id");
CREATE INDEX "work_order_checklist_responses_tid_idx" ON "work_order_checklist_responses" ("tenant_id");
CREATE INDEX "work_order_checklist_responses_tenant_id_idx" ON "work_order_checklist_responses" ("tenant_id");

CREATE TABLE "work_order_displacement_locations" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "latitude" numeric NOT NULL,
 "longitude" numeric NOT NULL,
 "recorded_at" datetime NOT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_displacement_locations_user_id_foreign" ON "work_order_displacement_locations" ("user_id");
CREATE INDEX "work_order_displacement_locations_wo_disp_loc_wo_rec_idx" ON "work_order_displacement_locations" ("work_order_id","recorded_at");
CREATE INDEX "work_order_displacement_locations_tenant_id_index" ON "work_order_displacement_locations" ("tenant_id");
CREATE INDEX "work_order_displacement_locations_tenant_id_idx" ON "work_order_displacement_locations" ("tenant_id");

CREATE TABLE "work_order_displacement_stops" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "type" varchar(30) NOT NULL,
 "started_at" datetime NOT NULL,
 "ended_at" datetime NULL DEFAULT NULL,
 "notes" text,
 "location_lat" numeric DEFAULT NULL,
 "location_lng" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_displacement_stops_work_order_id_foreign" ON "work_order_displacement_stops" ("work_order_id");
CREATE INDEX "work_order_displacement_stops_tenant_id_index" ON "work_order_displacement_stops" ("tenant_id");
CREATE INDEX "work_order_displacement_stops_tenant_id_idx" ON "work_order_displacement_stops" ("tenant_id");

CREATE TABLE "work_order_equipments" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "work_order_id" integer NOT NULL,
 "equipment_id" integer NOT NULL,
 "observations" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE INDEX "work_order_equipments_work_order_equipment_tenant_idx" ON "work_order_equipments" ("tenant_id");
CREATE INDEX "work_order_equipments_work_order_id_fk_idx" ON "work_order_equipments" ("work_order_id");
CREATE INDEX "work_order_equipments_equipment_id_fk_idx" ON "work_order_equipments" ("equipment_id");
CREATE INDEX "work_order_equipments_tenant_id_idx" ON "work_order_equipments" ("tenant_id");

CREATE TABLE "work_order_events" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "event_type" varchar(50) NOT NULL,
 "user_id" integer DEFAULT NULL,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_events_wo_events_wo_created_idx" ON "work_order_events" ("work_order_id","created_at");
CREATE INDEX "work_order_events_user_id_fk_idx" ON "work_order_events" ("user_id");
CREATE INDEX "work_order_events_tenant_id_index" ON "work_order_events" ("tenant_id");
CREATE INDEX "work_order_events_tenant_id_idx" ON "work_order_events" ("tenant_id");

CREATE TABLE "work_order_items" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "work_order_id" integer NOT NULL,
 "type" varchar(10) NOT NULL,
 "reference_id" integer DEFAULT NULL,
 "description" varchar(255) NOT NULL,
 "quantity" numeric NOT NULL DEFAULT '1.00',
 "unit_price" numeric NOT NULL DEFAULT '0.00',
 "discount" numeric NOT NULL DEFAULT '0.00',
 "total" numeric NOT NULL DEFAULT '0.00',
 "warehouse_id" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "cost_price" numeric DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "work_order_items_tenant_id_index" ON "work_order_items" ("tenant_id");
CREATE INDEX "work_order_items_work_order_id_fk_idx" ON "work_order_items" ("work_order_id");
CREATE INDEX "work_order_items_reference_id_fk_idx" ON "work_order_items" ("reference_id");
CREATE INDEX "work_order_items_warehouse_id_fk_idx" ON "work_order_items" ("warehouse_id");
CREATE INDEX "work_order_items_woi_woid_type_idx" ON "work_order_items" ("work_order_id","type");
CREATE INDEX "work_order_items_reference_id_index" ON "work_order_items" ("reference_id");
CREATE INDEX "work_order_items_work_order_id_type_index" ON "work_order_items" ("work_order_id","type");
CREATE INDEX "work_order_items_tenant_id_idx" ON "work_order_items" ("tenant_id");

CREATE TABLE "work_order_ratings" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "overall_rating" tinyint NOT NULL,
 "quality_rating" tinyint DEFAULT NULL,
 "punctuality_rating" tinyint DEFAULT NULL,
 "comment" text,
 "channel" varchar(30) NOT NULL DEFAULT 'link',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_ratings_work_order_id_fk_idx" ON "work_order_ratings" ("work_order_id");
CREATE INDEX "work_order_ratings_customer_id_fk_idx" ON "work_order_ratings" ("customer_id");
CREATE INDEX "work_order_ratings_tenant_id_index" ON "work_order_ratings" ("tenant_id");
CREATE INDEX "work_order_ratings_tenant_id_idx" ON "work_order_ratings" ("tenant_id");

CREATE TABLE "work_order_recurrences" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "customer_id" integer NOT NULL,
 "service_id" integer DEFAULT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "frequency" varchar NOT NULL,
 "interval" int NOT NULL DEFAULT '1',
 "day_of_month" int DEFAULT NULL,
 "day_of_week" int DEFAULT NULL,
 "start_date" date NOT NULL,
 "end_date" date DEFAULT NULL,
 "last_generated_at" datetime NULL DEFAULT NULL,
 "next_generation_date" date NOT NULL,
 "is_active" tinyint NOT NULL DEFAULT '1',
 "metadata" text DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_recurrences_customer_id_foreign" ON "work_order_recurrences" ("customer_id");
CREATE INDEX "work_order_recurrences_service_id_foreign" ON "work_order_recurrences" ("service_id");
CREATE INDEX "work_order_recurrences_tid_idx" ON "work_order_recurrences" ("tenant_id");
CREATE INDEX "work_order_recurrences_del_idx" ON "work_order_recurrences" ("deleted_at");
CREATE INDEX "work_order_recurrences_tenant_id_idx" ON "work_order_recurrences" ("tenant_id");
CREATE INDEX "work_order_recurrences_deleted_at_idx" ON "work_order_recurrences" ("deleted_at");

CREATE TABLE "work_order_signatures" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "signer_name" varchar(255) NOT NULL,
 "signer_document" varchar(255) DEFAULT NULL,
 "signer_type" varchar NOT NULL,
 "signature_data" text NOT NULL,
 "signed_at" datetime NOT NULL,
 "ip_address" varchar(255) DEFAULT NULL,
 "user_agent" varchar(255) DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_signatures_work_order_id_foreign" ON "work_order_signatures" ("work_order_id");
CREATE INDEX "work_order_signatures_tid_idx" ON "work_order_signatures" ("tenant_id");
CREATE INDEX "work_order_signatures_tenant_id_idx" ON "work_order_signatures" ("tenant_id");

CREATE TABLE "work_order_status_history" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "work_order_id" integer NOT NULL,
 "user_id" integer DEFAULT NULL,
 "from_status" varchar(30) DEFAULT NULL,
 "to_status" varchar(30) NOT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer NOT NULL
);
CREATE INDEX "work_order_status_history_tenant_id_index" ON "work_order_status_history" ("tenant_id");
CREATE INDEX "work_order_status_history_wosh_wo_created" ON "work_order_status_history" ("work_order_id","created_at");
CREATE INDEX "work_order_status_history_work_order_id_fk_idx" ON "work_order_status_history" ("work_order_id");
CREATE INDEX "work_order_status_history_user_id_fk_idx" ON "work_order_status_history" ("user_id");
CREATE INDEX "work_order_status_history_wosh_woid_cat_idx" ON "work_order_status_history" ("work_order_id","created_at");
CREATE INDEX "work_order_status_history_wo_status_history_wo_id_created_at_index" ON "work_order_status_history" ("work_order_id","created_at");
CREATE INDEX "work_order_status_history_tenant_id_idx" ON "work_order_status_history" ("tenant_id");

CREATE TABLE "work_order_technicians" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "work_order_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "role" varchar(20) NOT NULL DEFAULT 'tecnico',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "tenant_id" integer DEFAULT NULL
);
CREATE UNIQUE INDEX "work_order_technicians_work_order_id_user_id_unique" ON "work_order_technicians" ("work_order_id","user_id");
CREATE INDEX "work_order_technicians_user_id_foreign" ON "work_order_technicians" ("user_id");
CREATE INDEX "work_order_technicians_work_order_technicia_tenant_idx" ON "work_order_technicians" ("tenant_id");
CREATE INDEX "work_order_technicians_tenant_id_idx" ON "work_order_technicians" ("tenant_id");

CREATE TABLE "work_order_templates" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "name" varchar(255) NOT NULL,
 "description" text,
 "default_items" text DEFAULT NULL,
 "checklist_id" integer DEFAULT NULL,
 "priority" varchar(255) DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_templates_tenant_id_index" ON "work_order_templates" ("tenant_id");
CREATE INDEX "work_order_templates_checklist_id_foreign" ON "work_order_templates" ("checklist_id");
CREATE INDEX "work_order_templates_created_by_foreign" ON "work_order_templates" ("created_by");
CREATE INDEX "work_order_templates_wot_deleted_at" ON "work_order_templates" ("tenant_id","deleted_at");
CREATE INDEX "work_order_templates_del_idx" ON "work_order_templates" ("deleted_at");
CREATE INDEX "work_order_templates_tenant_id_idx" ON "work_order_templates" ("tenant_id");
CREATE INDEX "work_order_templates_deleted_at_idx" ON "work_order_templates" ("deleted_at");

CREATE TABLE "work_order_time_logs" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "work_order_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "started_at" datetime NOT NULL,
 "ended_at" datetime NULL DEFAULT NULL,
 "duration_seconds" int DEFAULT NULL,
 "activity_type" varchar(255) NOT NULL DEFAULT 'work',
 "description" text,
 "latitude" numeric DEFAULT NULL,
 "longitude" numeric DEFAULT NULL,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE INDEX "work_order_time_logs_work_order_id_foreign" ON "work_order_time_logs" ("work_order_id");
CREATE INDEX "work_order_time_logs_user_id_foreign" ON "work_order_time_logs" ("user_id");
CREATE INDEX "work_order_time_logs_wotl_tenant_wo_started" ON "work_order_time_logs" ("tenant_id","work_order_id","started_at");
CREATE INDEX "work_order_time_logs_wotl_tenant_user_started" ON "work_order_time_logs" ("tenant_id","user_id","started_at");
CREATE INDEX "work_order_time_logs_tid_idx" ON "work_order_time_logs" ("tenant_id");
CREATE INDEX "work_order_time_logs_tenant_id_idx" ON "work_order_time_logs" ("tenant_id");

CREATE TABLE "work_orders" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "number" varchar(20) NOT NULL,
 "customer_id" integer NOT NULL,
 "equipment_id" integer DEFAULT NULL,
 "branch_id" integer DEFAULT NULL,
 "created_by" integer DEFAULT NULL,
 "assigned_to" integer DEFAULT NULL,
 "status" varchar(30) NOT NULL DEFAULT 'open',
 "priority" varchar(10) NOT NULL DEFAULT 'normal',
 "description" text NOT NULL,
 "internal_notes" text,
 "technical_report" text,
 "received_at" datetime DEFAULT NULL,
 "started_at" datetime DEFAULT NULL,
 "completed_at" datetime DEFAULT NULL,
 "delivered_at" datetime DEFAULT NULL,
 "discount" numeric NOT NULL DEFAULT '0.00',
 "total" numeric NOT NULL DEFAULT '0.00',
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL,
 "deleted_at" datetime NULL DEFAULT NULL,
 "quote_id" integer DEFAULT NULL,
 "service_call_id" integer DEFAULT NULL,
 "seller_id" integer DEFAULT NULL,
 "driver_id" integer DEFAULT NULL,
 "os_number" varchar(30) DEFAULT NULL,
 "origin_type" varchar(20) DEFAULT NULL,
 "discount_percentage" numeric NOT NULL DEFAULT '0.00',
 "discount_amount" numeric NOT NULL DEFAULT '0.00',
 "signature_path" varchar(255) DEFAULT NULL,
 "signature_signer" varchar(255) DEFAULT NULL,
 "signature_at" datetime NULL DEFAULT NULL,
 "signature_ip" varchar(45) DEFAULT NULL,
 "displacement_value" numeric NOT NULL DEFAULT '0.00',
 "checklist_id" integer DEFAULT NULL,
 "sla_policy_id" integer DEFAULT NULL,
 "sla_due_at" datetime NULL DEFAULT NULL,
 "sla_responded_at" datetime NULL DEFAULT NULL,
 "sla_response_breached" tinyint DEFAULT NULL,
 "sla_resolution_breached" tinyint DEFAULT NULL,
 "recurring_contract_id" integer DEFAULT NULL,
 "dispatch_authorized_by" integer DEFAULT NULL,
 "dispatch_authorized_at" datetime NULL DEFAULT NULL,
 "business_number" varchar(50) DEFAULT NULL,
 "parent_id" integer DEFAULT NULL,
 "is_master" tinyint NOT NULL DEFAULT '0',
 "start_latitude" numeric DEFAULT NULL,
 "start_longitude" numeric DEFAULT NULL,
 "end_latitude" numeric DEFAULT NULL,
 "end_longitude" numeric DEFAULT NULL,
 "total_cost" numeric DEFAULT NULL,
 "profit_margin" numeric DEFAULT NULL,
 "difficulty_level" varchar(20) DEFAULT NULL,
 "is_paused" tinyint NOT NULL DEFAULT '0',
 "paused_at" datetime NULL DEFAULT NULL,
 "pause_reason" text,
 "cancellation_category" varchar(50) DEFAULT NULL,
 "cancellation_reason" text,
 "reschedule_count" int NOT NULL DEFAULT '0',
 "visit_number" int NOT NULL DEFAULT '1',
 "parent_work_order_id" integer DEFAULT NULL,
 "fleet_vehicle_id" integer DEFAULT NULL,
 "cost_center_id" integer DEFAULT NULL,
 "rating_token" varchar(64) DEFAULT NULL,
 "lead_source" varchar(30) DEFAULT NULL,
 "is_warranty" tinyint NOT NULL DEFAULT '0',
 "checkin_at" datetime NULL DEFAULT NULL,
 "checkin_lat" numeric DEFAULT NULL,
 "checkin_lng" numeric DEFAULT NULL,
 "checkout_at" datetime NULL DEFAULT NULL,
 "checkout_lat" numeric DEFAULT NULL,
 "checkout_lng" numeric DEFAULT NULL,
 "eta_minutes" int DEFAULT NULL,
 "auto_km_calculated" numeric DEFAULT NULL,
 "cancelled_at" datetime NULL DEFAULT NULL,
 "sla_deadline" datetime NULL DEFAULT NULL,
 "sla_hours" int DEFAULT NULL,
 "auto_assigned" tinyint NOT NULL DEFAULT '0',
 "auto_assignment_rule_id" integer DEFAULT NULL,
 "photo_checklist" text DEFAULT NULL,
 "reopen_count" int NOT NULL DEFAULT '0',
 "displacement_started_at" datetime NULL DEFAULT NULL,
 "displacement_arrived_at" datetime NULL DEFAULT NULL,
 "displacement_duration_minutes" int DEFAULT NULL,
 "agreed_payment_method" varchar(50) DEFAULT NULL,
 "agreed_payment_notes" varchar(500) DEFAULT NULL,
 "service_started_at" datetime NULL DEFAULT NULL,
 "wait_time_minutes" int DEFAULT NULL,
 "service_duration_minutes" int DEFAULT NULL,
 "total_duration_minutes" int DEFAULT NULL,
 "arrival_latitude" numeric DEFAULT NULL,
 "arrival_longitude" numeric DEFAULT NULL,
 "service_type" varchar(50) DEFAULT NULL,
 "service_modality" varchar(30) DEFAULT NULL,
 "requires_adjustment" tinyint NOT NULL DEFAULT '0',
 "requires_maintenance" tinyint NOT NULL DEFAULT '0',
 "client_wants_conformity_declaration" tinyint NOT NULL DEFAULT '0',
 "decision_rule_agreed" varchar(30) DEFAULT NULL,
 "subject_to_legal_metrology" tinyint NOT NULL DEFAULT '0',
 "needs_ipem_interaction" tinyint NOT NULL DEFAULT '0',
 "site_conditions" text,
 "calibration_scope_notes" text,
 "applicable_procedure" varchar(500) DEFAULT NULL,
 "will_emit_complementary_report" tinyint NOT NULL DEFAULT '0',
 "client_accepted_at" datetime DEFAULT NULL,
 "client_accepted_by" varchar(255) DEFAULT NULL,
 "manual_justification" text,
 "return_started_at" datetime NULL DEFAULT NULL,
 "return_arrived_at" datetime NULL DEFAULT NULL,
 "return_duration_minutes" int DEFAULT NULL,
 "return_destination" varchar(50) DEFAULT NULL,
 "delivery_forecast" date DEFAULT NULL,
 "tags" text DEFAULT NULL,
 "scheduled_date" datetime DEFAULT NULL,
 "address" varchar(255) DEFAULT NULL,
 "city" varchar(100) DEFAULT NULL,
 "state" varchar(2) DEFAULT NULL,
 "zip_code" varchar(10) DEFAULT NULL,
 "contact_phone" varchar(20) DEFAULT NULL,
 "project_id" integer DEFAULT NULL
);
CREATE UNIQUE INDEX "work_orders_tenant_id_number_unique" ON "work_orders" ("tenant_id","number");
CREATE UNIQUE INDEX "work_orders_rating_token_unique" ON "work_orders" ("rating_token");
CREATE INDEX "work_orders_customer_id_foreign" ON "work_orders" ("customer_id");
CREATE INDEX "work_orders_assigned_to_foreign" ON "work_orders" ("assigned_to");
CREATE INDEX "work_orders_tenant_id_status_index" ON "work_orders" ("tenant_id","status");
CREATE INDEX "work_orders_tenant_id_customer_id_index" ON "work_orders" ("tenant_id","customer_id");
CREATE INDEX "work_orders_checklist_id_foreign" ON "work_orders" ("checklist_id");
CREATE INDEX "work_orders_dispatch_authorized_by_foreign" ON "work_orders" ("dispatch_authorized_by");
CREATE INDEX "work_orders_tenant_id_business_number_index" ON "work_orders" ("tenant_id","business_number");
CREATE INDEX "work_orders_parent_work_order_id_foreign" ON "work_orders" ("parent_work_order_id");
CREATE INDEX "work_orders_auto_assignment_rule_id_foreign" ON "work_orders" ("auto_assignment_rule_id");
CREATE INDEX "work_orders_wo_tenant_created_idx" ON "work_orders" ("tenant_id","created_at");
CREATE INDEX "work_orders_wo_tenant_assigned" ON "work_orders" ("tenant_id","assigned_to");
CREATE INDEX "work_orders_wo_tenant_priority" ON "work_orders" ("tenant_id","priority");
CREATE INDEX "work_orders_wo_branch" ON "work_orders" ("branch_id");
CREATE INDEX "work_orders_wo_sla_policy" ON "work_orders" ("sla_policy_id");
CREATE INDEX "work_orders_created_by_foreign" ON "work_orders" ("created_by");
CREATE INDEX "work_orders_wo_deleted_at" ON "work_orders" ("tenant_id","deleted_at");
CREATE INDEX "work_orders_del_idx" ON "work_orders" ("deleted_at");
CREATE INDEX "work_orders_equipment_id_fk_idx" ON "work_orders" ("equipment_id");
CREATE INDEX "work_orders_branch_id_fk_idx" ON "work_orders" ("branch_id");
CREATE INDEX "work_orders_quote_id_fk_idx" ON "work_orders" ("quote_id");
CREATE INDEX "work_orders_service_call_id_fk_idx" ON "work_orders" ("service_call_id");
CREATE INDEX "work_orders_seller_id_fk_idx" ON "work_orders" ("seller_id");
CREATE INDEX "work_orders_driver_id_fk_idx" ON "work_orders" ("driver_id");
CREATE INDEX "work_orders_recurring_contract_i_fk_idx" ON "work_orders" ("recurring_contract_id");
CREATE INDEX "work_orders_parent_id_fk_idx" ON "work_orders" ("parent_id");
CREATE INDEX "work_orders_fleet_vehicle_id_fk_idx" ON "work_orders" ("fleet_vehicle_id");
CREATE INDEX "work_orders_cost_center_id_fk_idx" ON "work_orders" ("cost_center_id");
CREATE INDEX "work_orders_tenant_id_assigned_to_status_index" ON "work_orders" ("tenant_id","assigned_to","status");
CREATE INDEX "work_orders_project_id_foreign" ON "work_orders" ("project_id");
CREATE INDEX "work_orders_deleted_at_idx" ON "work_orders" ("deleted_at");

CREATE TABLE "work_schedules" (
 "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
 "tenant_id" integer NOT NULL,
 "user_id" integer NOT NULL,
 "date" date NOT NULL,
 "shift_type" varchar(30) NOT NULL DEFAULT 'normal',
 "start_time" time DEFAULT NULL,
 "end_time" time DEFAULT NULL,
 "region" varchar(100) DEFAULT NULL,
 "notes" text,
 "created_at" datetime NULL DEFAULT NULL,
 "updated_at" datetime NULL DEFAULT NULL
);
CREATE UNIQUE INDEX "work_schedules_user_id_date_unique" ON "work_schedules" ("user_id","date");
CREATE INDEX "work_schedules_tid_idx" ON "work_schedules" ("tenant_id");
CREATE INDEX "work_schedules_tenant_id_idx" ON "work_schedules" ("tenant_id");

-- Migration records
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (1, '0001_01_01_000000_create_users_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (2, '0001_01_01_000001_create_cache_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (3, '0001_01_01_000002_create_jobs_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (4, '2025_02_10_090000_add_missing_columns_to_products', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (5, '2026_02_07_200000_create_tenant_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (6, '2026_02_07_200001_add_tenant_fields_to_users', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (7, '2026_02_07_223816_create_permission_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (8, '2026_02_07_230000_create_rbac_extensions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (9, '2026_02_07_300000_create_cadastros_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (10, '2026_02_07_400000_create_work_order_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (11, '2026_02_07_500000_create_technician_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (12, '2026_02_07_600000_create_financial_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (13, '2026_02_07_700000_create_commission_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (14, '2026_02_07_800000_create_expense_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (15, '2026_02_07_900000_create_audit_settings_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (16, '2026_02_07_950001_create_email_accounts_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (17, '2026_02_07_950002_create_emails_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (18, '2026_02_07_950003_create_email_attachments_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (19, '2026_02_07_950004_create_email_rules_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (20, '2026_02_08_024851_create_personal_access_tokens_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (21, '2026_02_08_100000_create_quotes_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (22, '2026_02_08_100001_add_soft_deletes_to_schedules_and_time_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (23, '2026_02_08_200000_create_service_calls_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (24, '2026_02_08_300000_alter_work_orders_add_origin', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (25, '2026_02_08_400000_create_technician_cash_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (26, '2026_02_08_500000_alter_commission_rules_v2', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (27, '2026_02_08_600000_create_imports_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (28, '2026_02_08_700000_alter_equipments_v2', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (29, '2026_02_08_800000_create_notifications_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (30, '2026_02_08_900000_alter_customers_crm_fields', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (31, '2026_02_08_900001_create_crm_pipelines_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (32, '2026_02_08_900002_create_crm_deals_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (33, '2026_02_08_900003_create_crm_activities_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (34, '2026_02_08_900004_create_crm_messages_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (35, '2026_02_09_000001_add_cost_price_to_work_order_items_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (36, '2026_02_09_000002_add_dashboard_cashflow_dre_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (37, '2026_02_09_000003_add_signature_and_recurring_contracts', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (38, '2026_02_09_000004_create_work_order_attachments_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (39, '2026_02_09_100000_add_missing_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (40, '2026_02_09_100001_add_displacement_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (41, '2026_02_09_100002_add_revision_to_quotes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (42, '2026_02_09_100003_create_invoices_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (43, '2026_02_09_100004_create_account_payable_categories_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (44, '2026_02_09_100005_create_suppliers_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (45, '2026_02_09_100006_add_tenant_id_to_child_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (46, '2026_02_09_100007_create_payment_methods_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (47, '2026_02_09_100008_create_price_histories_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (48, '2026_02_09_180000_add_trial_status_to_tenants', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (49, '2026_02_09_200000_add_tenant_id_to_work_order_items_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (50, '2026_02_09_200001_create_stock_movements_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (51, '2026_02_09_203805_add_created_by_to_service_calls_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (52, '2026_02_09_300000_create_bank_reconciliation_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (53, '2026_02_09_300001_create_chart_of_accounts', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (54, '2026_02_09_300002_alter_recurring_contracts_billing', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (55, '2026_02_09_400000_create_service_checklists_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (56, '2026_02_09_400001_enrich_calibration_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (57, '2026_02_09_400002_create_sla_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (58, '2026_02_09_500000_brainstorm_gaps_enhancements', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (59, '2026_02_09_500001_create_client_portal_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (60, '2026_02_09_600000_create_commission_advanced_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (61, '2026_02_09_700000_create_central_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (62, '2026_02_09_700001_add_central_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (63, '2026_02_09_700002_create_central_rules_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (64, '2026_02_09_700003_add_central_phase3_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (65, '2026_02_09_800000_add_recurring_contract_id_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (66, '2026_02_09_900100_add_rejection_reason_to_expenses_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (67, '2026_02_09_999999_add_roles_name_guard_unique_for_sqlite_compat', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (68, '2026_02_10_000001_add_tenant_id_to_roles', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (69, '2026_02_10_060000_make_branch_code_nullable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (70, '2026_02_10_070000_fix_branches_unique_constraint', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (71, '2026_02_10_100000_add_tenant_to_quote_children', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (72, '2026_02_10_120000_add_soft_deletes_to_expense_categories', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (73, '2026_02_10_120001_add_tenant_id_to_technician_cash_transactions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (74, '2026_02_10_120002_add_soft_deletes_to_technician_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (75, '2026_02_10_120003_add_tenant_id_to_work_order_related_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (76, '2026_02_10_235959_add_missing_report_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (77, '2026_02_10_600000_add_imports_indexes_and_fix_fk', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (78, '2026_02_10_600002_add_separator_to_imports', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (79, '2026_02_10_700000_add_imported_ids_to_imports', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (80, '2026_02_10_900000_add_tenant_id_to_crm_stages', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (81, '2026_02_10_999998_add_technician_cash_report_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (82, '2026_02_11_000001_add_technician_cash_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (83, '2026_02_11_000100_add_fk_constraints_to_expenses_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (84, '2026_02_11_004400_add_tenant_branch_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (85, '2026_02_11_010000_add_estoque_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (86, '2026_02_11_100000_fix_seller_id_on_delete_quotes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (87, '2026_02_11_200000_add_budget_limit_to_expense_categories', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (88, '2026_02_11_200001_add_resolution_and_comments_to_service_calls', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (89, '2026_02_11_200100_create_expense_status_history_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (90, '2026_02_11_235959_add_customers_report_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (91, '2026_02_11_600000_add_import_delete_permission', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (92, '2026_02_11_700000_add_original_name_to_imports', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (93, '2026_02_11_700004_add_notification_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (94, '2026_02_11_800000_create_inmetro_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (95, '2026_02_12_000000_add_branch_id_to_users', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (96, '2026_02_12_000001_add_description_to_roles_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (97, '2026_02_12_120001_add_inmetro_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (98, '2026_02_12_160001_grant_central_manage_rules_to_operational_roles', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (99, '2026_02_12_200000_create_standard_weights_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (100, '2026_02_12_200001_add_affects_net_value_to_expenses', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (101, '2026_02_12_200002_add_standard_weight_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (102, '2026_02_12_220000_add_trade_name_to_customers', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (103, '2026_02_12_220001_create_fiscal_notes_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (104, '2026_02_12_221000_create_push_subscriptions_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (105, '2026_02_12_222000_add_format_to_bank_statements', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (106, '2026_02_13_135200_add_inmetro_config_to_tenants', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (107, '2026_02_13_140000_resolve_system_gaps_batch1', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (108, '2026_02_13_150000_fix_missing_columns_from_tests', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (109, '2026_02_13_150001_inmetro_v2_expansion', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (110, '2026_02_13_160000_create_inmetro_base_configs_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (111, '2026_02_13_170000_inmetro_v3_50features', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (112, '2026_02_13_220923_create_employee_benefits_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (113, '2026_02_13_225949_create_cameras_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (114, '2026_02_13_230535_add_location_and_status_to_users_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (115, '2026_02_13_232218_create_checklists_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (116, '2026_02_13_232235_create_checklist_submissions_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (117, '2026_02_13_233642_add_google_maps_link_to_customers_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (118, '2026_02_13_300000_create_bank_accounts_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (119, '2026_02_13_300001_create_fund_transfers_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (120, '2026_02_13_300002_add_fund_transfer_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (121, '2026_02_13_300003_enhance_bank_reconciliation', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (122, '2026_02_14_000000_create_nps_responses_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (123, '2026_02_14_000001_create_auvo_imports_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (124, '2026_02_14_000002_add_auvo_import_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (125, '2026_02_14_000003_create_parts_kits_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (126, '2026_02_14_000005_create_hr_trainings_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (127, '2026_02_14_000006_create_hr_organization_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (128, '2026_02_14_000007_create_hr_skills_matrix_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (129, '2026_02_14_000008_create_hr_feedback_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (130, '2026_02_14_000009_add_fields_to_hr_trainings_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (131, '2026_02_14_000010_create_hr_recruitment_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (132, '2026_02_14_000011_create_service_skills_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (133, '2026_02_14_000100_create_work_order_recurrences_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (134, '2026_02_14_000410_create_work_order_time_logs_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (135, '2026_02_14_000526_create_work_order_signatures_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (136, '2026_02_14_001826_create_inmetro_seals_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (137, '2026_02_14_002737_create_warehouses_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (138, '2026_02_14_002738_create_batches_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (139, '2026_02_14_002740_create_warehouse_stocks_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (140, '2026_02_14_002741_create_product_serials_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (141, '2026_02_14_002828_add_stock_options_to_products_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (142, '2026_02_14_002829_add_warehouse_to_stock_movements_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (143, '2026_02_14_003614_create_vehicle_insurances_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (144, '2026_02_14_003636_add_advanced_fleet_fields_to_vehicles_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (145, '2026_02_14_003638_create_vehicle_tires_v2_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (146, '2026_02_14_003650_create_toll_and_gps_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (147, '2026_02_14_003739_create_vehicle_tires_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (148, '2026_02_14_003740_create_fuel_logs_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (149, '2026_02_14_003741_create_vehicle_pool_requests_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (150, '2026_02_14_003742_create_vehicle_accidents_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (151, '2026_02_14_003838_add_parent_id_to_work_orders_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (152, '2026_02_14_004114_create_product_kits_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (153, '2026_02_14_004331_create_work_order_chats_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (154, '2026_02_14_004341_create_inventory_tables_v3', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (155, '2026_02_14_004500_create_inventory_tables_manual', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (156, '2026_02_14_010000_add_eccentricity_data_to_equipment_calibrations', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (157, '2026_02_14_010001_create_tool_checkouts_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (158, '2026_02_14_015000_create_stock_integration_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (159, '2026_02_14_020000_create_financial_advanced_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (160, '2026_02_14_030000_create_stock_advanced_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (161, '2026_02_14_040000_create_crm_advanced_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (162, '2026_02_14_050000_create_lab_advanced_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (163, '2026_02_14_060000_create_portal_integration_security_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (164, '2026_02_14_070000_create_remaining_module_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (165, '2026_02_14_100000_add_200_features_batch1_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (166, '2026_02_14_100001_add_200_features_batch2_columns', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (167, '2026_02_14_100002_add_200_features_batch3_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (168, '2026_02_14_100003_add_lead_source_to_quotes_and_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (169, '2026_02_14_160000_create_reconciliation_rules_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (170, '2026_02_14_160001_add_audit_fields_to_bank_statement_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (171, '2026_02_14_200000_create_hr_advanced_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (172, '2026_02_14_200001_add_hr_advanced_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (173, '2026_02_14_300000_add_remaining_module_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (174, '2026_02_15_000001_create_email_advanced_features_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (175, '2026_02_15_034516_fix_missing_indexes_and_constraints', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (176, '2026_02_15_100000_add_source_filter_and_settlement_id_to_commissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (177, '2026_02_16_000001_fix_performance_reviews_schema', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (178, '2026_02_16_000002_add_title_to_performance_reviews', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (179, '2026_02_16_000003_add_coordinates_to_customers_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (180, '2026_02_16_000010_add_display_name_to_roles_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (181, '2026_02_16_000020_fix_role_names_technician_to_tecnico', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (182, '2026_02_16_000030_add_tenant_id_to_cameras_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (183, '2026_02_16_000040_add_payment_method_to_technician_cash', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (184, '2026_02_16_000050_add_is_warranty_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (185, '2026_02_16_000060_add_default_affects_net_value_to_expense_categories', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (186, '2026_02_16_100000_add_75_features_comprehensive', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (187, '2026_02_16_100001_create_system_improvements_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (188, '2026_02_16_100002_create_crm_features_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (189, '2026_02_16_200000_add_business_fields_to_tenants', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (190, '2026_02_16_200001_add_rejection_and_cash_to_fueling_logs', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (191, '2026_02_16_200002_add_enrichment_fields_to_customers', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (192, '2026_02_16_200003_create_technician_fund_requests_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (193, '2026_02_16_300000_create_crm_field_management_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (194, '2026_02_16_310000_add_alert_central_features', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (195, '2026_02_16_400000_add_name_to_scheduled_reports', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (196, '2026_02_16_500000_add_paid_amount_to_commission_settlements', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (197, '2026_02_17_000001_add_progress_to_imports', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (198, '2026_02_17_000002_add_cancelled_at_and_tenant_to_wo_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (199, '2026_02_17_000010_add_denied_permissions_to_users_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (200, '2026_02_17_000020_add_displacement_value_to_quotes_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (201, '2026_02_17_100000_add_warehouse_technician_and_vehicle_support', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (202, '2026_02_17_100001_add_stock_transfer_acceptance_and_used_items', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (203, '2026_02_17_100002_create_warranty_tracking_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (204, '2026_02_17_100003_fix_commission_settlements_columns', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (205, '2026_02_17_100004_fix_commission_goals_columns', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (206, '2026_02_17_100005_fix_commission_rules_user_nullable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (207, '2026_02_17_110000_add_remind_notified_at_to_central_items', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (208, '2026_02_17_120000_quality_management_review_and_complaint_dates', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (209, '2026_02_18_010000_expand_fiscal_notes_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (210, '2026_02_18_010100_create_fiscal_events_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (211, '2026_02_18_010200_add_fiscal_config_to_tenants', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (212, '2026_02_18_060000_create_fiscal_extended_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (213, '2026_02_18_100000_add_label_fields_to_products', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (214, '2026_02_18_100001_create_contracts_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (215, '2026_02_18_100002_create_finance_stock_crm_bi_features', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (216, '2026_02_18_100003_create_hr_contracts_portal_metrology_infra_features', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (217, '2026_02_18_100004_create_service_ops_features', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (218, '2026_02_18_100005_quote_module_improvements', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (219, '2026_02_18_110000_add_displacement_tracking_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (220, '2026_02_18_120000_add_chamados_improvements', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (221, '2026_02_18_130000_add_wizard_fields_to_calibrations', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (222, '2026_02_18_500000_create_central_subtasks_and_attachments', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (223, '2026_02_18_500001_create_central_time_entries_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (224, '2026_02_18_500002_add_recurrence_to_central_items', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (225, '2026_02_18_500003_create_central_dependencies_and_escalation', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (226, '2026_02_19_010000_add_supplier_id_to_accounts_payable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (227, '2026_02_19_034000_make_fiscal_events_fiscal_note_id_nullable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (228, '2026_02_19_100000_add_certificate_normative_fields', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (229, '2026_02_19_100001_create_equipment_models_and_parts', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (230, '2026_02_19_120000_create_service_catalog_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (231, '2026_02_19_130000_create_lookup_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (232, '2026_02_19_235229_add_missing_foreign_keys_phase1', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (233, '2026_02_20_100000_create_routes_planning_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (234, '2026_02_20_100001_add_qr_fields_for_pwa_scanner', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (235, '2026_02_20_100003_create_qa_alerts_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (236, '2026_02_20_100004_create_auxiliary_tools_and_qr_fields', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (237, '2026_02_20_100005_create_financial_module_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (238, '2026_02_20_100006_create_modules_4_5_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (239, '2026_02_20_200000_add_wear_metrics_to_standard_weights', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (240, '2026_02_23_100000_add_sla_due_at_to_service_calls', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (241, '2026_02_23_100001_add_agreed_payment_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (242, '2026_02_23_110000_add_break_fields_to_time_clock_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (243, '2026_02_24_100000_enable_spatie_teams_tenant_isolation', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (244, '2026_02_24_100001_add_google_maps_link_to_service_calls', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (245, '2026_02_24_200000_create_central_item_watchers_and_notification_prefs', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (246, '2026_02_24_200001_create_central_templates_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (247, '2026_02_24_200002_add_pwa_mode_to_notification_prefs', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (248, '2026_02_24_200003_add_installation_testing_to_quotes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (249, '2026_02_24_300000_refactor_service_call_statuses_and_os_execution', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (250, '2026_02_24_300001_add_return_trip_columns_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (251, '2026_02_24_400000_add_contact_id_to_crm_activities', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (252, '2026_02_24_500000_create_additional_lookup_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (253, '2026_02_24_510000_create_customer_company_sizes_and_customer_ratings_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (254, '2026_02_24_600000_create_work_order_approvals_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (255, '2026_02_25_120000_create_extended_lookup_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (256, '2026_02_25_200000_backfill_fleet_operational_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (257, '2026_02_25_220000_create_automation_report_lookup_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (258, '2026_02_25_230000_create_supplier_contract_payment_frequencies_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (259, '2026_02_26_100000_add_tenant_id_to_pivot_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (260, '2026_02_26_100001_add_missing_composite_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (261, '2026_02_26_100002_add_soft_deletes_to_stock_and_commission', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (262, '2026_02_26_100003_normalize_duplicate_columns', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (263, '2026_02_26_100004_drop_equipments_public_qr_hash', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (264, '2026_02_26_100005_add_tenant_id_to_inventory_items', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (265, '2026_02_26_100006_add_tenant_id_to_extra_pivot_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (266, '2026_02_27_100000_add_tenant_id_to_remaining_pivot_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (267, '2026_02_28_040000_add_inmetro_v4_urgency_fields', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (268, '2026_02_28_220700_add_cnab_fields_to_accounts_receivable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (269, '2026_02_28_220800_add_fiscal_fields_to_invoices', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (270, '2026_02_28_220900_add_invoice_id_to_accounts_receivable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (271, '2026_02_28_230000_create_quality_corrective_actions_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (272, '2026_03_01_184000_add_missing_columns_to_user_2fa_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (273, '2026_03_02_100000_add_missing_columns_to_equipments_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (274, '2026_03_02_113313_create_work_order_templates_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (275, '2026_03_02_200000_fix_crm_web_forms_slug_unique_per_tenant', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (276, '2026_03_02_230000_rename_central_permissions_to_agenda', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (277, '2026_03_03_165600_add_tenant_id_to_service_call_comments', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (278, '2026_03_04_221710_add_warehouse_id_to_work_order_items_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (279, '2026_03_05_140000_add_google_calendar_columns_to_users_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (280, '2026_03_05_145000_add_approval_channel_and_location_ie_fields', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (281, '2026_03_06_120000_add_transaction_id_to_bank_statement_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (282, '2026_03_06_144257_add_status_to_technician_cash_funds', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (283, '2026_03_09_100000_add_missing_foreign_keys', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (284, '2026_03_09_110000_add_score_to_crm_deals_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (285, '2026_03_11_120000_add_service_ops_metadata_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (286, '2026_03_11_160000_add_scheduled_date_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (287, '2026_03_12_090000_add_technician_cash_self_service_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (288, '2026_03_12_180000_add_description_to_debt_renegotiations_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (289, '2026_03_12_230000_fix_central_item_watchers_legacy_foreign_key', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (290, '2026_03_13_120000_add_delivery_tracking_to_quote_emails', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (291, '2026_03_14_140947_create_telescope_entries_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (292, '2026_03_15_000001_add_missing_performance_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (293, '2026_03_15_000002_add_critical_composite_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (294, '2026_03_15_000003_add_reimbursement_ap_id_to_expenses_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (295, '2026_03_16_000001_add_missing_columns_to_multiple_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (296, '2026_03_16_000001_add_remaining_performance_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (297, '2026_03_16_100000_infra_audit_missing_indexes_and_fk', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (298, '2026_03_16_110000_fix_missing_columns_for_tests', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (299, '2026_03_16_200000_fix_critical_fk_on_delete_behavior', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (300, '2026_03_16_200001_add_soft_delete_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (301, '2026_03_16_300000_infra_audit_final_composite_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (302, '2026_03_16_400000_infra_audit_additional_performance_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (303, '2026_03_16_500000_create_portal_tickets_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (304, '2026_03_16_500001_create_portal_ticket_messages_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (305, '2026_03_16_500001_create_tax_calculations_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (306, '2026_03_16_500002_create_rr_studies_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (307, '2026_03_16_600000_update_equipment_status_to_english', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (308, '2026_03_16_600001_update_standard_weight_status_to_english', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (309, '2026_03_16_600002_update_crm_activity_channels_to_english', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (310, '2026_03_16_600003_update_agenda_enums_to_english', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (311, '2026_03_17_070000_add_missing_columns_for_tests', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (312, '2026_03_17_100000_infra_audit_tenant_id_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (313, '2026_03_17_100001_infra_audit_soft_delete_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (314, '2026_03_17_100002_infra_audit_critical_fk_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (315, '2026_03_17_100003_infra_audit_tenant_status_composite_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (316, '2026_03_18_000001_infra_audit_v2_performance_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (317, '2026_03_18_000002_infra_audit_v3_performance_indexes', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (318, '2026_03_19_200000_add_enrichment_data_to_inmetro_owners', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (319, '2026_03_20_110000_add_deleted_at_to_tenants_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (320, '2026_03_20_120000_fix_commission_schema_legacy_residue', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (321, '2026_03_20_120000_reconcile_accounts_payable_supplier_fk', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (322, '2026_03_20_130000_reconcile_equipments_status_default', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (323, '2026_03_20_163500_reconcile_remaining_legacy_equipment_statuses', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (324, '2026_03_20_180000_add_attachment_path_to_continuous_feedback', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (325, '2026_03_21_100000_create_sync_conflict_logs_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (326, '2026_03_21_100001_add_sla_breach_fields_to_service_calls', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (327, '2026_03_21_100002_create_business_hours_and_holidays_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (328, '2026_03_21_100003_add_composite_indexes_to_work_order_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (329, '2026_03_21_100004_add_notification_preferences_to_customers', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (330, '2026_03_21_100005_create_user_favorites_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (331, '2026_03_21_100006_create_qr_scans_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (332, '2026_03_21_200000_add_tenant_id_to_work_order_event_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (333, '2026_03_22_100000_add_missing_fk_to_quotes_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (334, '2026_03_22_100001_add_quote_export_invoice_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (335, '2026_03_22_130000_add_ncm_to_products_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (336, '2026_03_22_165114_add_payment_method_to_technician_fund_requests', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (337, '2026_03_22_172730_add_quote_id_to_accounts_receivable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (338, '2026_03_22_200000_add_contact_address_fields_to_work_orders', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (339, '2026_03_22_200000_add_extended_fields_to_products_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (340, '2026_03_22_212400_migrate_logo_to_tenant_settings', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (341, '2026_03_23_001948_add_metadata_to_work_order_events_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (342, '2026_03_23_005219_add_logo_path_to_tenants_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (343, '2026_03_23_125647_add_advanced_finance_fields_to_payables_receivables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (344, '2026_03_23_200000_add_tenant_id_to_hr_models_missing_tenant', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (345, '2026_03_23_200001_add_hash_chain_to_time_clock_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (346, '2026_03_23_200002_add_labor_fields_to_users', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (347, '2026_03_23_200003_create_labor_tax_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (348, '2026_03_23_200004_create_employee_dependents_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (349, '2026_03_23_200005_create_payroll_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (350, '2026_03_23_200006_add_precision_class_to_equipment_calibrations', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (351, '2026_03_23_200006_create_rescissions_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (352, '2026_03_23_200007_create_esocial_tables', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (353, '2026_03_23_223631_add_general_conditions_to_quotes_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (354, '2026_03_23_223739_add_credit_limit_to_technician_cash_funds_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (355, '2026_03_23_300001_add_payroll_fields_to_expenses_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (356, '2026_03_23_300001_create_technician_feedbacks_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (357, '2026_03_23_300002_create_seal_applications_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (358, '2026_03_23_300002_seed_granular_hr_permissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (359, '2026_03_24_000001_add_clt_compliance_fields_to_journey_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (360, '2026_03_24_000001_add_work_order_id_to_accounts_payable', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (361, '2026_03_24_000002_add_agreement_type_to_journey_rules', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (362, '2026_03_24_000003_create_hour_bank_transactions_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (363, '2026_03_24_000004_add_signing_key_to_tenants', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (364, '2026_03_24_001419_add_location_tracking_to_time_clock_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (365, '2026_03_24_001420_add_rep_p_fields_to_tenants', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (366, '2026_03_24_001421_seed_2026_tax_brackets', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (367, '2026_03_24_001952_create_espelho_confirmations_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (368, '2026_03_24_002046_create_clt_violations_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (369, '2026_03_24_014700_add_archived_at_to_time_clock_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (370, '2026_03_24_100001_create_time_clock_audit_logs_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (371, '2026_03_24_100002_add_confirmation_fields_to_time_clock_entries', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (372, '2026_03_24_100003_add_benefit_deduction_fields_to_payroll_lines', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (373, '2026_03_24_100004_add_negative_deduction_to_journey_rules', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (374, '2026_03_24_100005_create_esocial_rubrics_table', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (375, '2026_03_24_100006_add_hour_bank_and_benefit_deduction_fields_to_payroll_lines', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (376, '2026_03_24_200001_add_advance_hour_bank_to_rescissions', 1);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (377, '2026_03_26_300001_add_unique_tenant_number_to_fiscal_invoices', 2);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (378, '2026_03_26_100000_add_deep_scrape_fields_to_inmetro', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (379, '2026_03_26_105915_create_tv_dashboard_configs_table', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (380, '2026_03_26_110849_create_portal_guest_links_table', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (381, '2026_03_26_120000_create_helpdesk_entities_tables', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (382, '2026_03_26_200001_create_repair_seal_batches_table', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (383, '2026_03_26_200002_evolve_inmetro_seals_for_repair_module', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (384, '2026_03_26_200003_create_repair_seal_assignments_table', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (385, '2026_03_26_200004_create_psei_submissions_table', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (386, '2026_03_26_200005_create_repair_seal_alerts_table', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (387, '2026_03_26_400001_add_retry_fields_to_esocial_events', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (388, '2026_03_26_400001_create_non_conformities_table', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (389, '2026_03_26_400002_create_mobile_entities_tables', 3);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (390, '2026_03_26_160219_add_sla_columns_to_portal_tickets_table', 4);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (391, '2026_03_26_160222_create_sla_policies_table', 4);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (392, '2026_03_26_161317_create_admissions_table', 4);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (393, '2026_03_26_180649_create_user_competencies_table', 5);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (394, '2026_03_26_182723_alter_corrective_actions_sourceable_nullable', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (395, '2026_03_26_191100_add_gateway_columns_to_payments_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (396, '2026_03_27_000000_create_projects_table_and_add_work_order_link', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (397, '2026_03_27_000001_create_operational_snapshots_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (398, '2026_03_27_000100_create_analytics_datasets_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (399, '2026_03_27_000101_create_data_export_jobs_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (400, '2026_03_27_000102_create_embedded_dashboards_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (401, '2026_03_27_010000_create_project_supporting_tables', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (402, '2026_03_27_180000_add_tenant_id_to_purchase_quotation_items_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (403, '2026_03_27_183000_add_single_use_and_consumed_at_to_portal_guest_links_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (404, '2026_03_27_184000_add_missing_analytics_columns_to_fuel_logs_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (405, '2026_03_27_190000_create_asset_records_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (406, '2026_03_27_191000_create_depreciation_logs_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (407, '2026_03_27_192000_create_asset_disposals_table', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (408, '2026_03_27_193000_add_phase15_columns_and_tables_for_fixed_assets', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (409, '2026_03_28_020000_align_legacy_webhooks_table_with_runtime_contract', 6);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (410, '2026_04_02_150000_add_tenant_id_to_crm_web_form_submissions_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (411, '2026_04_02_160000_add_psp_columns_to_account_receivable_installments_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (412, '2026_04_02_180000_upgrade_whatsapp_messages_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (413, '2026_04_02_200000_add_ema_fields_to_calibration_readings', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (414, '2026_04_02_210000_create_lgpd_tables', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (415, '2026_04_02_220000_create_saas_billing_tables', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (416, '2026_04_06_100000_create_crm_funnel_automations_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (417, '2026_04_09_100000_add_tenant_id_to_central_item_history_and_comments', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (418, '2026_04_09_100000_add_tenant_id_to_crm_child_tables', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (419, '2026_04_09_100001_update_whatsapp_messages_table_columns', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (420, '2026_04_09_203215_add_asaas_id_to_customers_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (421, '2026_04_09_210000_add_critical_analysis_fields_to_work_orders', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (422, '2026_04_09_210001_add_traceability_fields_to_standard_weights', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (423, '2026_04_09_210002_create_maintenance_reports_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (424, '2026_04_09_210003_create_certificate_emission_checklists_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (425, '2026_04_09_220000_create_journey_days_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (426, '2026_04_09_220001_create_journey_blocks_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (427, '2026_04_09_220002_create_journey_policies_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (428, '2026_04_09_220003_create_hour_bank_policies_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (429, '2026_04_09_220004_create_journey_approvals_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (430, '2026_04_09_220005_create_travel_requests_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (431, '2026_04_09_220006_create_overnight_stays_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (432, '2026_04_09_220007_create_travel_advances_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (433, '2026_04_09_220008_create_travel_expense_reports_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (434, '2026_04_09_220009_create_technician_certifications_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (435, '2026_04_09_220010_create_biometric_consents_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (436, '2026_04_09_220011_create_offline_sync_logs_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (437, '2026_04_09_230000_expand_journey_entries_for_motor', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (438, '2026_04_09_230001_expand_journey_rules_for_motor', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (439, '2026_04_09_230002_add_journey_entry_id_to_blocks_and_approvals', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (440, '2026_04_10_100001_add_normative_fields_to_equipment_calibrations', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (441, '2026_04_10_100002_add_normative_fields_to_work_orders', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (442, '2026_04_10_100003_add_environmental_to_calibration_readings', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (443, '2026_04_10_110000_make_legacy_whatsapp_columns_nullable', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (444, '2026_04_10_120000_ensure_contract_addendums_and_measurements_tables', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (445, '2026_04_10_200001_create_linearity_tests_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (446, '2026_04_10_210001_add_decision_rule_parameters_to_equipment_calibrations', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (447, '2026_04_10_210002_add_decision_result_to_equipment_calibrations', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (448, '2026_04_10_210003_create_calibration_decision_logs_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (449, '2026_04_10_300001_create_accreditation_scopes_table', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (450, '2026_04_10_500000_fix_production_schema_drifts', 7);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (451, '2026_04_17_120000_add_document_hash_for_encrypted_search', 8);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (452, '2026_04_17_140000_add_tenant_id_to_tenant_safe_tables', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (453, '2026_04_17_150000_backfill_tenant_id_and_make_not_null', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (454, '2026_04_17_160000_revert_tenant_id_not_null_on_pivots', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (455, '2026_04_17_170000_add_tenant_id_indexes_to_remaining_tables', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (456, '2026_04_17_180000_add_deleted_at_indexes_to_remaining_tables', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (457, '2026_04_17_190000_add_tenant_id_indexes_wave2e', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (458, '2026_04_17_200000_add_hardening_to_client_portal_users', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (459, '2026_04_17_210000_add_updated_by_deleted_by_to_financial_tables', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (460, '2026_04_17_220000_normalize_monetary_precision', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (461, '2026_04_17_230000_add_unique_composite_for_documents', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (462, '2026_04_17_240000_normalize_standard_weight_shape_to_english', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (463, '2026_04_17_250000_normalize_calibration_result_to_english', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (464, '2026_04_17_260000_drop_pt_columns_from_customer_locations', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (465, '2026_04_17_270000_drop_user_id_from_expenses', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (466, '2026_04_17_280000_rename_user_id_to_created_by_in_travel_expense_reports', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (467, '2026_04_17_290000_normalize_central_enums_defaults_to_english', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (468, '2026_04_17_300000_rename_central_pt_columns_to_english', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (469, '2026_04_17_310000_rename_central_source_to_origin', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (470, '2026_04_17_320000_rename_central_rules_pt_columns', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (471, '2026_04_17_330000_normalize_visit_report_visit_type_to_english', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (472, '2026_04_17_400000_fix_encryption_regression_user_2fa_and_tenant_fiscal', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (473, '2026_04_17_410000_finish_central_pt_to_en_rename_attachments_subtasks_templates', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (474, '2026_04_17_420000_normalize_work_orders_priority_to_medium', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (475, '2026_04_18_500001_repair_encrypted_search_alter_on_mysql', 9);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (476, '2026_04_18_500002_restore_amount_paid_default', 10);
INSERT INTO "migrations" ("id", "migration", "batch") VALUES (477, '2026_04_18_500003_invalidate_legacy_backup_codes', 11);
