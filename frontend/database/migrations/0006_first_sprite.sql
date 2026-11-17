ALTER TABLE `orders` ADD `client_token` varchar(36);--> statement-breakpoint
ALTER TABLE `orders` ADD CONSTRAINT `orders_client_token_uq` UNIQUE(`user_id`,`client_token`);