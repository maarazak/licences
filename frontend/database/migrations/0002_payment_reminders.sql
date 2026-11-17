ALTER TABLE `orders` ADD `reminder_count` int DEFAULT 0 NOT NULL;--> statement-breakpoint
ALTER TABLE `orders` ADD `last_reminder_at` datetime;--> statement-breakpoint
ALTER TABLE `users` ADD `mail_reminders` boolean DEFAULT true NOT NULL;