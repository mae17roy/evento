-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 13, 2025 at 01:09 AM
-- Server version: 10.4.24-MariaDB
-- PHP Version: 8.1.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `event_management_system`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `complete_old_bookings` ()   BEGIN
    -- Update status of old confirmed bookings to completed
    UPDATE bookings
    SET status = 'completed'
    WHERE status = 'confirmed' 
    AND booking_date < CURDATE() - INTERVAL 1 DAY;
    
    -- Mark related notifications as read
    UPDATE notifications n
    JOIN bookings b ON n.related_id = b.id AND n.type = 'booking'
    SET n.is_read = 1
    WHERE b.status = 'completed';
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `create_booking_notification` (IN `booking_id` INT)   BEGIN
    DECLARE client_id INT;
    DECLARE owner_id INT;
    DECLARE service_name VARCHAR(100);
    DECLARE booking_status VARCHAR(20);
    DECLARE booking_date DATE;
    DECLARE booking_time TIME;
    
    -- Get booking information
    SELECT 
        b.user_id, 
        s.owner_id,
        s.name,
        b.status,
        b.booking_date,
        b.booking_time
    INTO 
        client_id, 
        owner_id,
        service_name,
        booking_status,
        booking_date,
        booking_time
    FROM bookings b
    JOIN booking_items bi ON b.id = bi.booking_id
    JOIN services s ON bi.service_id = s.id
    WHERE b.id = booking_id
    LIMIT 1;
    
    -- Create notification for client
    INSERT INTO notifications (
        user_id, 
        type, 
        title, 
        message, 
        related_id, 
        created_at
    ) VALUES (
        client_id,
        'booking',
        CONCAT('Booking #', booking_id, ' Created'),
        CONCAT('Your booking for ', service_name, ' on ', DATE_FORMAT(booking_date, '%M %e, %Y'), ' at ', TIME_FORMAT(booking_time, '%h:%i %p'), ' has been received and is currently ', booking_status, '.'),
        booking_id,
        NOW()
    );
    
    -- Create notification for service owner
    INSERT INTO notifications (
        user_id,
        owner_id,
        type,
        title,
        message,
        related_id,
        created_at
    ) VALUES (
        NULL,
        owner_id,
        'booking',
        CONCAT('New Booking #', booking_id),
        CONCAT('You have received a new booking for ', service_name, ' on ', DATE_FORMAT(booking_date, '%M %e, %Y'), ' at ', TIME_FORMAT(booking_time, '%h:%i %p'), '.'),
        booking_id,
        NOW()
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `refresh_services_data` ()   BEGIN
    -- Update views_count based on bookings (simulated metric)
    UPDATE services s
    SET views_count = (
        SELECT COUNT(*) * 5 + FLOOR(RAND() * 20)
        FROM booking_items bi
        WHERE bi.service_id = s.id
    );
    
    -- Update featured services periodically
    UPDATE services
    SET featured = 0;
    
    UPDATE services
    SET featured = 1
    WHERE id IN (
        SELECT id FROM (
            SELECT s.id
            FROM services s
            LEFT JOIN reviews r ON s.id = r.service_id
            GROUP BY s.id
            ORDER BY AVG(COALESCE(r.rating, 3)) DESC, RAND()
            LIMIT 8
        ) as top_services
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_notes`
--

CREATE TABLE `admin_notes` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'user_id of admin who added the note',
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `availability`
--

CREATE TABLE `availability` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `special_requests` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Triggers `bookings`
--
DELIMITER $$
CREATE TRIGGER `after_booking_insert` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
    CALL create_booking_notification(NEW.id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_booking_status_update` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO booking_status_history (booking_id, status, changed_by)
        VALUES (NEW.id, NEW.status, @admin_user_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Triggers `booking_items`
--
DELIMITER $$
CREATE TRIGGER `after_booking_item_insert` AFTER INSERT ON `booking_items` FOR EACH ROW BEGIN
    -- Update the last_booked_at timestamp for the service
    UPDATE services
    SET last_booked_at = NOW()
    WHERE id = NEW.service_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `booking_status_history`
--

CREATE TABLE `booking_status_history` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `status` enum('pending','confirmed','cancelled','completed') NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(11) DEFAULT NULL COMMENT 'user_id of admin who changed status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `owner_id`, `name`, `description`, `image`, `created_at`, `updated_at`) VALUES
(0, 0, 'Catering', 'Food services for your events', NULL, '2025-03-11 13:44:39', '2025-03-11 13:44:39'),
(1, NULL, 'Catering', 'foods', 'wedding.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09'),
(2, NULL, 'Photo Studio', 'photo booth', 'corporate.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09'),
(3, NULL, 'Sound System', 'Disco', 'birthday.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09'),
(4, NULL, 'Souvenir', 'gifts', 'conference.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09'),
(5, NULL, 'Decoration', 'decorators/designer', NULL, '2025-03-09 14:09:51', '2025-03-10 15:57:30'),
(33, 9, 'Catering', 'foods', 'wedding.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(34, 10, 'Catering', 'foods', 'wedding.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(35, 9, 'Decoration', 'decorators/designer', NULL, '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(36, 10, 'Decoration', 'decorators/designer', NULL, '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(37, 9, 'Photo Studio', 'photo booth', 'corporate.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(38, 10, 'Photo Studio', 'photo booth', 'corporate.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(39, 9, 'Sound System', 'Disco', 'birthday.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(40, 10, 'Sound System', 'Disco', 'birthday.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(41, 9, 'Souvenir', 'gifts', 'conference.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17'),
(42, 10, 'Souvenir', 'gifts', 'conference.jpg', '2025-03-11 02:31:17', '2025-03-11 02:31:17');

-- --------------------------------------------------------

--
-- Table structure for table `global_categories`
--

CREATE TABLE `global_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `global_categories`
--

INSERT INTO `global_categories` (`id`, `name`, `description`, `image`, `created_at`, `updated_at`) VALUES
(1, 'Catering', 'foods', 'wedding.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09'),
(2, 'Decoration', 'decorators/designer', NULL, '2025-03-09 14:09:51', '2025-03-09 14:09:51'),
(3, 'Photo Studio', 'photo booth', 'corporate.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09'),
(4, 'Sound System', 'Disco', 'birthday.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09'),
(5, 'Souvenir', 'gifts', 'conference.jpg', '2025-03-08 01:47:17', '2025-03-09 07:30:09');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL COMMENT 'booking, service, etc.',
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL COMMENT 'ID of related item (booking_id, service_id, etc.)',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `owner_id`, `type`, `title`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(0, 10, 10, 'service', 'New Service Added', 'Your service \'romel music\' has been added at price 5,000.00', 0, 0, '2025-03-12 01:35:18'),
(21, 9, NULL, 'system', 'Welcome to EVENTO', 'Welcome to EVENTO! Start by adding your services.', NULL, 1, '2025-03-10 16:09:02'),
(22, 9, 9, 'service', 'New Service Added', 'Your service \'light studio\' has been added at price 599.00', 22, 1, '2025-03-10 17:26:08'),
(23, 10, NULL, 'system', 'Welcome to EVENTO', 'Welcome to EVENTO! Start by adding your services.', NULL, 1, '2025-03-10 17:27:41'),
(24, 10, 10, 'service', 'New Service Added', 'Your service \'saw v near\' has been added at price 123,456.00', 23, 1, '2025-03-10 17:29:37'),
(25, 10, 10, 'service', 'New Service Added', 'Your service \'OKAY NA TOH\' has been added at price 99,999,999.99', 24, 1, '2025-03-11 02:33:14'),
(26, 11, NULL, 'system', 'Welcome to EVENTO', 'Welcome to EVENTO! Start exploring event services.', NULL, 0, '2025-03-11 06:19:41');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `tile_class` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `featured` tinyint(1) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `last_booked_at` timestamp NULL DEFAULT NULL,
  `availability_status` enum('Available','Unavailable','Coming Soon') NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `owner_id`, `category_id`, `name`, `description`, `price`, `image`, `is_available`, `featured`, `views_count`, `last_booked_at`, `availability_status`, `created_at`, `updated_at`) VALUES
(22, 9, 37, 'light studio', 'photo booth', '599.00', 'default-service.jpg', 0, 1, 12, NULL, 'Unavailable', '2025-03-10 17:26:08', '2025-03-12 16:37:38'),
(23, 10, 42, 'saw v near', 'personalized handcrafted souvenirs', '123456.00', 'service_67cf2181ad12c.png', 1, 1, 12, NULL, 'Available', '2025-03-10 17:29:37', '2025-03-12 16:37:38'),
(24, 10, 34, 'OKAY NA TOH', 'BASTA MASARAP', '99999999.99', 'service_67cfa0eac3100.jpg', 1, 1, 4, NULL, 'Coming Soon', '2025-03-11 02:33:14', '2025-03-12 16:37:38'),
(0, 10, 40, 'romel music', '', '5000.00', 'default-service.jpg', 1, 1, 3, NULL, 'Coming Soon', '2025-03-12 01:35:18', '2025-03-12 16:37:38');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `updated_at`) VALUES
(1, 'EVENTO', 'EVENTO', 'Company name displayed on the dashboard', '2025-03-08 23:05:46'),
(2, 'company_email', 'contact@elegantevents.com', 'Primary contact email', '2025-03-08 12:12:15'),
(3, 'company_phone', '+1234567890', 'Primary contact phone', '2025-03-08 12:12:15'),
(4, 'notification_email', 'notifications@elegantevents.com', 'Email used for sending notifications', '2025-03-08 12:12:15'),
(5, 'booking_confirmation_template', 'Dear {customer_name},\n\nYour booking has been confirmed for {service_name} on {booking_date} at {booking_time}.\n\nThank you for choosing Elegant Events.', 'Email template for booking confirmations', '2025-03-08 12:12:15'),
(6, 'booking_cancellation_template', 'Dear {customer_name},\n\nYour booking for {service_name} on {booking_date} at {booking_time} has been cancelled.\n\nPlease contact us if you have any questions.', 'Email template for booking cancellations', '2025-03-08 12:12:15'),
(7, 'db_version', '1.1', 'Current database schema version', '2025-03-08 12:12:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `business_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','client','owner') DEFAULT 'client',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `address`, `business_name`, `role`, `created_at`, `updated_at`) VALUES
(1, 'mae', 'mayet@gmail.com', '123456', '234', 'luna', NULL, 'client', '2025-03-10 18:59:16', '2025-03-10 18:59:16'),
(9, 'cater u', 'roy@gmail.com', 'roy123456', '09832456', 'suyo, luna la union', NULL, 'owner', '2025-03-10 16:09:01', '2025-03-10 16:09:01'),
(10, 'babushka.co', 'ba@gmail.com', '123456', '123456', 'baguio2600', NULL, 'owner', '2025-03-10 17:27:41', '2025-03-10 17:27:41'),
(11, 'bea', 'bea@gmail.com', '$2y$10$xgYOTHg5.DfTMATSIXi16u8/OzN/fWhG2HOSk7qpv8eZwc260Fs22', '123456', 'sfc', NULL, 'client', '2025-03-11 06:19:41', '2025-03-11 06:19:41'),
(0, 'Bea Faye Antalan', 'beya@gmail.com', '$2y$10$Jphi7I4tWpCyEo7HsXazVu6Wee80RwMG/DrM9liKTvb6d0TKWFg0S', '', '', NULL, 'client', '2025-03-11 13:41:22', '2025-03-11 13:41:22'),
(0, 'Barts', 'josh@gmail.com', '$2y$10$XTobhSfL.eEq1d7o8wXmsO8G3COasyLOuTmTZx0VYiVE1cD/cedka', '', 'Lingsat, City of San Fernando, La Union', 'BARts Fud haus', 'owner', '2025-03-11 13:44:39', '2025-03-11 13:44:39'),
(0, 'Evento Admin', 'evento@gmail.com', 'admin123', '09053887135', 'Lingsat, City of San Fernado, La Union', 'EVENTO', 'admin', '2025-03-11 16:08:33', '2025-03-11 16:08:33'),
(0, 'Evento Admin', 'evento@gmail.com', 'admin123', '09053887135', 'Lingsat, City of San Fernado, La Union', 'EVENTO', 'admin', '2025-03-11 16:08:42', '2025-03-11 16:08:42');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `after_owner_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'owner' THEN
        -- Check if the category already exists before creating it
        -- Using INSERT IGNORE to skip duplicates
        INSERT IGNORE INTO categories (owner_id, name, description, created_at)
        VALUES
        (NEW.id, 'Catering', 'Food services for your events', NOW()),
        (NEW.id, 'Photo Studio', 'Photography services', NOW()),
        (NEW.id, 'Sound System', 'Audio equipment and DJ services', NOW()),
        (NEW.id, 'Souvenir', 'Gift items and mementos', NOW()),
        (NEW.id, 'Decoration', 'decorators/designer', NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_services_with_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_services_with_details` (
`id` int(11)
,`name` varchar(100)
,`description` text
,`price` decimal(10,2)
,`image` varchar(255)
,`is_available` tinyint(1)
,`availability_status` enum('Available','Unavailable','Coming Soon')
,`featured` tinyint(1)
,`views_count` int(11)
,`last_booked_at` timestamp
,`created_at` timestamp
,`updated_at` timestamp
,`category_name` varchar(100)
,`category_id` int(11)
,`owner_id` int(11)
,`owner_name` varchar(100)
,`business_name` varchar(100)
,`owner_email` varchar(100)
,`owner_phone` varchar(20)
,`review_count` bigint(21)
,`avg_rating` decimal(7,4)
,`booking_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_services_with_details`
--
DROP TABLE IF EXISTS `vw_services_with_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_services_with_details`  AS SELECT `s`.`id` AS `id`, `s`.`name` AS `name`, `s`.`description` AS `description`, `s`.`price` AS `price`, `s`.`image` AS `image`, `s`.`is_available` AS `is_available`, `s`.`availability_status` AS `availability_status`, `s`.`featured` AS `featured`, `s`.`views_count` AS `views_count`, `s`.`last_booked_at` AS `last_booked_at`, `s`.`created_at` AS `created_at`, `s`.`updated_at` AS `updated_at`, coalesce(`gc`.`name`,`c`.`name`) AS `category_name`, coalesce(`gc`.`id`,`c`.`id`) AS `category_id`, `u`.`id` AS `owner_id`, `u`.`name` AS `owner_name`, `u`.`business_name` AS `business_name`, `u`.`email` AS `owner_email`, `u`.`phone` AS `owner_phone`, (select count(0) from `reviews` `r` where `r`.`service_id` = `s`.`id`) AS `review_count`, (select avg(`r`.`rating`) from `reviews` `r` where `r`.`service_id` = `s`.`id`) AS `avg_rating`, (select count(0) from (`booking_items` `bi` join `bookings` `b` on(`bi`.`booking_id` = `b`.`id`)) where `bi`.`service_id` = `s`.`id` and `b`.`status` in ('confirmed','completed')) AS `booking_count` FROM (((`services` `s` left join `categories` `c` on(`s`.`category_id` = `c`.`id`)) left join `global_categories` `gc` on(`s`.`category_id` = `gc`.`id`)) join `users` `u` on(`s`.`owner_id` = `u`.`id`))  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notes`
--
ALTER TABLE `admin_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `availability`
--
ALTER TABLE `availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `availability_ibfk_1` (`service_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_bookings_status` (`status`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `booking_status_history`
--
ALTER TABLE `booking_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categories_owner` (`owner_id`);

--
-- Indexes for table `global_categories`
--
ALTER TABLE `global_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_owner_notifications` (`owner_id`,`is_read`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
