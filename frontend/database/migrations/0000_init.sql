CREATE TABLE `audit_logs` (
	`id` varchar(36) NOT NULL,
	`admin_id` varchar(36) NOT NULL,
	`admin_email` varchar(191) NOT NULL,
	`action` varchar(100) NOT NULL,
	`target` varchar(191) NOT NULL DEFAULT '',
	`details` varchar(1000) NOT NULL DEFAULT '',
	`ip` varchar(64),
	`created_at` datetime NOT NULL,
	CONSTRAINT `audit_logs_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `login_codes` (
	`id` varchar(36) NOT NULL,
	`email` varchar(191) NOT NULL,
	`code_hash` varchar(64) NOT NULL,
	`attempts` int NOT NULL DEFAULT 0,
	`expires_at` datetime NOT NULL,
	`consumed_at` datetime,
	`ip` varchar(64),
	`created_at` datetime NOT NULL,
	CONSTRAINT `login_codes_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `order_events` (
	`id` varchar(36) NOT NULL,
	`order_id` varchar(36) NOT NULL,
	`type` varchar(64) NOT NULL,
	`message` varchar(500) NOT NULL,
	`actor` varchar(191) NOT NULL DEFAULT 'system',
	`created_at` datetime NOT NULL,
	CONSTRAINT `order_events_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `orders` (
	`id` varchar(36) NOT NULL,
	`reference` varchar(24) NOT NULL,
	`user_id` varchar(36) NOT NULL,
	`product_id` varchar(36) NOT NULL,
	`product_name` varchar(255) NOT NULL,
	`supplier_product_id` varchar(64) NOT NULL,
	`quantity` int NOT NULL,
	`unit_price_xof` int NOT NULL,
	`total_xof` int NOT NULL,
	`unit_cost_usd_cents` int NOT NULL,
	`usd_rate` int NOT NULL,
	`warranty` varchar(100) NOT NULL DEFAULT '',
	`expire_days` int NOT NULL DEFAULT 0,
	`customer_first_name` varchar(100) NOT NULL,
	`customer_last_name` varchar(100) NOT NULL,
	`customer_phone` varchar(32),
	`customer_country` varchar(2),
	`activation_emails` json,
	`status` enum('pending_payment','paid','processing','activation_awaiting','delivered','failed','payment_failed','expired','cancelled','refunded') NOT NULL DEFAULT 'pending_payment',
	`payment_checkout_id` varchar(191),
	`payment_intent_id` varchar(191),
	`payment_url` varchar(2048),
	`paid_at` datetime,
	`paid_with` varchar(16) NOT NULL DEFAULT 'gateway',
	`supplier_idem_key` varchar(64) NOT NULL,
	`supplier_order_id` varchar(64),
	`supplier_reference` varchar(64),
	`supplier_status` varchar(64),
	`fulfil_attempts` int NOT NULL DEFAULT 0,
	`fulfil_locked_at` datetime,
	`delivery_encrypted` text,
	`delivered_at` datetime,
	`failure_code` varchar(64),
	`failure_message` varchar(500),
	`refund_requested_at` datetime,
	`refunded_at` datetime,
	`refund_method` varchar(16),
	`admin_note` text,
	`created_at` datetime NOT NULL,
	`updated_at` datetime NOT NULL,
	CONSTRAINT `orders_id` PRIMARY KEY(`id`),
	CONSTRAINT `orders_reference_uq` UNIQUE(`reference`),
	CONSTRAINT `orders_intent_uq` UNIQUE(`payment_intent_id`),
	CONSTRAINT `orders_checkout_uq` UNIQUE(`payment_checkout_id`)
);
--> statement-breakpoint
CREATE TABLE `products` (
	`id` varchar(36) NOT NULL,
	`supplier_id` varchar(64) NOT NULL,
	`slug` varchar(191) NOT NULL,
	`name` varchar(255) NOT NULL,
	`category` varchar(100) NOT NULL DEFAULT '',
	`image_url` varchar(1024),
	`delivery_kind` varchar(64) NOT NULL DEFAULT '',
	`cost_usd_cents` int NOT NULL,
	`list_usd_cents` int NOT NULL DEFAULT 0,
	`stock` int,
	`in_stock` boolean NOT NULL DEFAULT false,
	`preorder` boolean NOT NULL DEFAULT false,
	`expire_days` int NOT NULL DEFAULT 0,
	`warranty` varchar(100) NOT NULL DEFAULT '',
	`requires_email` boolean NOT NULL DEFAULT false,
	`supplier_purchasable` boolean NOT NULL DEFAULT false,
	`supplier_active` boolean NOT NULL DEFAULT true,
	`is_published` boolean NOT NULL DEFAULT false,
	`is_featured` boolean NOT NULL DEFAULT false,
	`display_name` varchar(255),
	`description` text,
	`instructions` text,
	`price_xof_override` int,
	`margin_percent_override` int,
	`synced_at` datetime NOT NULL,
	`created_at` datetime NOT NULL,
	`updated_at` datetime NOT NULL,
	CONSTRAINT `products_id` PRIMARY KEY(`id`),
	CONSTRAINT `products_supplier_uq` UNIQUE(`supplier_id`),
	CONSTRAINT `products_slug_uq` UNIQUE(`slug`)
);
--> statement-breakpoint
CREATE TABLE `rate_limits` (
	`key` varchar(191) NOT NULL,
	`count` int NOT NULL DEFAULT 0,
	`reset_at` datetime NOT NULL,
	CONSTRAINT `rate_limits_key` PRIMARY KEY(`key`)
);
--> statement-breakpoint
CREATE TABLE `sessions` (
	`id` varchar(36) NOT NULL,
	`token_hash` varchar(64) NOT NULL,
	`user_id` varchar(36) NOT NULL,
	`expires_at` datetime NOT NULL,
	`ip` varchar(64),
	`user_agent` varchar(255),
	`created_at` datetime NOT NULL,
	CONSTRAINT `sessions_id` PRIMARY KEY(`id`),
	CONSTRAINT `sessions_token_uq` UNIQUE(`token_hash`)
);
--> statement-breakpoint
CREATE TABLE `settings` (
	`key` varchar(64) NOT NULL,
	`value` text NOT NULL,
	`updated_at` datetime NOT NULL,
	CONSTRAINT `settings_key` PRIMARY KEY(`key`)
);
--> statement-breakpoint
CREATE TABLE `stock_alerts` (
	`id` varchar(36) NOT NULL,
	`user_id` varchar(36) NOT NULL,
	`product_id` varchar(36) NOT NULL,
	`notified_at` datetime,
	`created_at` datetime NOT NULL,
	CONSTRAINT `stock_alerts_id` PRIMARY KEY(`id`),
	CONSTRAINT `stock_alerts_user_product_uq` UNIQUE(`user_id`,`product_id`)
);
--> statement-breakpoint
CREATE TABLE `ticket_messages` (
	`id` varchar(36) NOT NULL,
	`ticket_id` varchar(36) NOT NULL,
	`author_id` varchar(36) NOT NULL,
	`is_staff` boolean NOT NULL DEFAULT false,
	`body` text NOT NULL,
	`created_at` datetime NOT NULL,
	CONSTRAINT `ticket_messages_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE TABLE `tickets` (
	`id` varchar(36) NOT NULL,
	`reference` varchar(24) NOT NULL,
	`user_id` varchar(36) NOT NULL,
	`order_id` varchar(36),
	`subject` varchar(200) NOT NULL,
	`status` enum('open','answered','closed') NOT NULL DEFAULT 'open',
	`created_at` datetime NOT NULL,
	`updated_at` datetime NOT NULL,
	CONSTRAINT `tickets_id` PRIMARY KEY(`id`),
	CONSTRAINT `tickets_reference_uq` UNIQUE(`reference`)
);
--> statement-breakpoint
CREATE TABLE `users` (
	`id` varchar(36) NOT NULL,
	`email` varchar(191) NOT NULL,
	`first_name` varchar(100),
	`last_name` varchar(100),
	`phone` varchar(32),
	`country` varchar(2),
	`balance_xof` int NOT NULL DEFAULT 0,
	`role` enum('customer','admin') NOT NULL DEFAULT 'customer',
	`is_blocked` boolean NOT NULL DEFAULT false,
	`last_login_at` datetime,
	`created_at` datetime NOT NULL,
	CONSTRAINT `users_id` PRIMARY KEY(`id`),
	CONSTRAINT `users_email_uq` UNIQUE(`email`)
);
--> statement-breakpoint
CREATE TABLE `wallet_entries` (
	`id` varchar(36) NOT NULL,
	`user_id` varchar(36) NOT NULL,
	`amount_xof` int NOT NULL,
	`balance_after_xof` int NOT NULL,
	`kind` varchar(32) NOT NULL,
	`order_id` varchar(36),
	`label` varchar(255) NOT NULL,
	`actor` varchar(191) NOT NULL DEFAULT 'system',
	`created_at` datetime NOT NULL,
	CONSTRAINT `wallet_entries_id` PRIMARY KEY(`id`)
);
--> statement-breakpoint
CREATE INDEX `audit_logs_created_idx` ON `audit_logs` (`created_at`);--> statement-breakpoint
CREATE INDEX `login_codes_email_idx` ON `login_codes` (`email`,`created_at`);--> statement-breakpoint
CREATE INDEX `order_events_order_idx` ON `order_events` (`order_id`,`created_at`);--> statement-breakpoint
CREATE INDEX `orders_user_idx` ON `orders` (`user_id`,`created_at`);--> statement-breakpoint
CREATE INDEX `orders_status_idx` ON `orders` (`status`,`updated_at`);--> statement-breakpoint
CREATE INDEX `products_listing_idx` ON `products` (`is_published`,`supplier_active`,`category`);--> statement-breakpoint
CREATE INDEX `sessions_user_idx` ON `sessions` (`user_id`);--> statement-breakpoint
CREATE INDEX `stock_alerts_pending_idx` ON `stock_alerts` (`notified_at`,`product_id`);--> statement-breakpoint
CREATE INDEX `ticket_messages_ticket_idx` ON `ticket_messages` (`ticket_id`,`created_at`);--> statement-breakpoint
CREATE INDEX `tickets_user_idx` ON `tickets` (`user_id`,`updated_at`);--> statement-breakpoint
CREATE INDEX `tickets_status_idx` ON `tickets` (`status`,`updated_at`);--> statement-breakpoint
CREATE INDEX `wallet_entries_user_idx` ON `wallet_entries` (`user_id`,`created_at`);