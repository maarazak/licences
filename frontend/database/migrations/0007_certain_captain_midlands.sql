DROP INDEX `products_listing_idx` ON `products`;--> statement-breakpoint
ALTER TABLE `audit_logs` MODIFY COLUMN `created_at` datetime(3) NOT NULL;--> statement-breakpoint
ALTER TABLE `order_events` MODIFY COLUMN `created_at` datetime(3) NOT NULL;--> statement-breakpoint
ALTER TABLE `wallet_entries` MODIFY COLUMN `created_at` datetime(3) NOT NULL;--> statement-breakpoint
ALTER TABLE `wallet_entries` ADD CONSTRAINT `wallet_entries_order_kind_uq` UNIQUE(`order_id`,`kind`);--> statement-breakpoint
CREATE INDEX `products_listing_idx` ON `products` (`is_published`,`supplier_active`,`shop_category`);