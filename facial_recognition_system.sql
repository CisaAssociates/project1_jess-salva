-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 30, 2025 at 03:47 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `facial_recognition_system`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddNewUser` (IN `p_first_name` VARCHAR(50), IN `p_last_name` VARCHAR(50), IN `p_email` VARCHAR(100), IN `p_phone` VARCHAR(20), IN `p_role` VARCHAR(50), IN `p_face_image_path` VARCHAR(255))   BEGIN
    DECLARE new_user_id INT;
    
    -- Insert the user
    INSERT INTO users (first_name, last_name, email, phone, role)
    VALUES (p_first_name, p_last_name, p_email, p_phone, p_role);
    
    -- Get the new user ID
    SET new_user_id = LAST_INSERT_ID();
    
    -- Return the new user ID (face encoding needs to be added via application code)
    SELECT new_user_id AS user_id, p_face_image_path AS face_image_path;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `LogAccessAttempt` (IN `p_user_id` INT, IN `p_face_recognized` BOOLEAN, IN `p_rfid_scanned` VARCHAR(50), IN `p_access_granted` BOOLEAN, IN `p_confidence_score` FLOAT, IN `p_device_id` VARCHAR(50))   BEGIN
    INSERT INTO accesslogs (user_id, face_recognized, rfid_scanned, access_granted, confidence_score, device_id)
    VALUES (p_user_id, p_face_recognized, p_rfid_scanned, p_access_granted, p_confidence_score, p_device_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RegisterRFIDCard` (IN `p_card_id` VARCHAR(50), IN `p_user_id` INT)   BEGIN
    -- Check if card already exists
    DECLARE card_exists INT;
    SELECT COUNT(*) INTO card_exists FROM rfidcards WHERE card_id = p_card_id;
    
    IF card_exists > 0 THEN
        -- Update existing card
        UPDATE rfidcards 
        SET user_id = p_user_id, 
            is_active = TRUE,
            revoked_at = NULL
        WHERE card_id = p_card_id;
    ELSE
        -- Insert new card
        INSERT INTO rfidcards (card_id, user_id)
        VALUES (p_card_id, p_user_id);
    END IF;
    
    -- Return the updated card info
    SELECT r.card_id, CONCAT(u.first_name, ' ', u.last_name) AS user_name, r.is_active
    FROM rfidcards r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.card_id = p_card_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `accesslogs`
--

CREATE TABLE `accesslogs` (
  `log_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `face_recognized` tinyint(1) DEFAULT NULL,
  `rfid_scanned` varchar(50) DEFAULT NULL,
  `access_granted` tinyint(1) NOT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `confidence_score` float DEFAULT NULL,
  `device_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `accesslogs`
--

INSERT INTO `accesslogs` (`log_id`, `user_id`, `face_recognized`, `rfid_scanned`, `access_granted`, `timestamp`, `confidence_score`, `device_id`) VALUES
(970, NULL, 1, NULL, 0, '2025-04-27 07:46:10', 0, 'device001'),
(971, NULL, 1, NULL, 0, '2025-04-27 07:46:17', 0, 'device001'),
(972, NULL, 1, NULL, 0, '2025-04-27 07:46:55', 0, 'device001'),
(973, 105, 1, NULL, 0, '2025-04-27 07:47:09', 62.615, 'device001'),
(974, 105, 1, NULL, 0, '2025-04-27 07:47:23', 66.588, 'device001'),
(975, 106, 1, NULL, 0, '2025-04-27 07:47:40', 9.3189, 'device001'),
(976, 106, 1, NULL, 0, '2025-04-27 07:47:54', 53.8149, 'device001'),
(977, NULL, 1, NULL, 0, '2025-04-27 07:52:35', 0, 'device001'),
(978, NULL, 1, NULL, 0, '2025-04-27 07:52:43', 0, 'device001'),
(979, NULL, 1, NULL, 0, '2025-04-27 07:52:50', 0, 'device001'),
(980, NULL, 1, NULL, 0, '2025-04-27 07:52:57', 0, 'device001'),
(981, NULL, 1, NULL, 0, '2025-04-27 07:53:04', 0, 'device001'),
(982, NULL, 1, NULL, 0, '2025-04-27 07:53:12', 0, 'device001'),
(983, NULL, 1, NULL, 0, '2025-04-27 07:53:19', 0, 'device001'),
(984, NULL, 1, NULL, 0, '2025-04-27 07:53:26', 0, 'device001'),
(985, NULL, 1, NULL, 0, '2025-04-27 07:53:33', 0, 'device001'),
(986, NULL, 0, NULL, 0, '2025-04-27 08:02:11', 0, 'device001'),
(987, NULL, 0, NULL, 0, '2025-04-27 08:02:18', 0, 'device001'),
(988, NULL, 0, NULL, 0, '2025-04-27 08:02:25', 0, 'device001'),
(989, NULL, 0, NULL, 0, '2025-04-27 08:02:32', 0, 'device001'),
(990, NULL, 0, NULL, 0, '2025-04-27 08:02:39', 0, 'device001'),
(991, NULL, 0, NULL, 0, '2025-04-27 08:02:46', 0, 'device001'),
(992, NULL, 0, NULL, 0, '2025-04-27 08:02:54', 0, 'device001'),
(993, NULL, 0, NULL, 0, '2025-04-27 08:03:01', 0, 'device001'),
(994, NULL, 0, NULL, 0, '2025-04-27 08:20:45', 0, 'device001'),
(995, NULL, 0, NULL, 0, '2025-04-27 08:20:52', 0, 'device001'),
(996, NULL, 0, NULL, 0, '2025-04-27 08:21:00', 0, 'device001'),
(997, NULL, 0, NULL, 0, '2025-04-27 08:21:07', 0, 'device001'),
(998, NULL, 0, NULL, 0, '2025-04-27 08:21:14', 0, 'device001'),
(999, NULL, 0, NULL, 0, '2025-04-27 08:21:21', 0, 'device001'),
(1000, NULL, 0, NULL, 0, '2025-04-27 08:21:39', 0, 'device001'),
(1001, NULL, 0, NULL, 0, '2025-04-27 08:21:46', 0, 'device001'),
(1002, NULL, 0, NULL, 0, '2025-04-27 08:21:53', 0, 'device001'),
(1003, 38, 1, NULL, 0, '2025-04-30 03:24:23', 27.8519, 'device001'),
(1004, 38, 1, NULL, 0, '2025-04-30 03:24:37', 25.2739, 'device001'),
(1005, 38, 1, '621402', 0, '2025-04-30 03:30:42', 17.9285, 'device001'),
(1006, 38, 1, '3b687e4', 0, '2025-04-30 03:30:51', 25.5256, 'device001'),
(1007, 38, 1, '4c5eea7', 0, '2025-04-30 03:31:15', 12.4919, 'device001'),
(1008, 38, 1, '8c86c11', 0, '2025-04-30 03:31:22', 28.9971, 'device001'),
(1009, NULL, 0, NULL, 0, '2025-04-30 03:32:00', 0, 'device001'),
(1010, NULL, 0, NULL, 0, '2025-04-30 03:32:07', 0, 'device001'),
(1011, NULL, 0, NULL, 0, '2025-04-30 03:32:14', 0, 'device001'),
(1012, 106, 1, '4c5eea7', 1, '2025-04-30 03:32:22', 60.7163, 'device001');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `device_id` varchar(50) NOT NULL,
  `location` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `last_active` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`device_id`, `location`, `description`, `ip_address`, `last_active`, `status`) VALUES
('device001', 'school', 'naa sa bits department', '', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `faceencodings`
--

CREATE TABLE `faceencodings` (
  `encoding_id` int NOT NULL,
  `user_id` int NOT NULL,
  `face_encoding` blob,
  `face_image_path` varchar(255) NOT NULL,
  `quality_score` float DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `faceencodings`
--

INSERT INTO `faceencodings` (`encoding_id`, `user_id`, `face_encoding`, `face_image_path`, `quality_score`, `created_at`) VALUES
(73, 105, 0x0000004028e8c7bf000000c0ec72ba3f000000406167aa3f00000080572dbfbf00000000712cc8bf000000c047a1b2bf000000a0a85dbbbf00000060cd43a9bf000000a0ae7dc23f000000802149c1bf000000609084c23f000000e0f025a4bf00000020178fc8bf00000040079ba2bf000000c0f69cbcbf00000020b934d23f000000e00003d1bf000000403becc4bf00000060ec658a3f0000008097cf93bf000000c039acb33f000000802f89ab3f000000c08ded713f00000020f55da93f00000040902fbdbf000000c094cfd4bf000000e0a0b4bdbf00000020e7138bbf00000000e056a3bf000000a01099a6bf000000e076a9a0bf000000405d3fb13f000000e01990c7bf00000080f92492bf00000060094eb33f000000200bedc23f0000006040f1933f000000c01a8cb0bf000000805603be3f00000000c063a83f00000040fcaccebf000000207302963f0000006098e9c43f000000408338ce3f0000002065abc43f00000040377d983f000000a05f90a23f000000c00886c3bf00000020baa8ba3f000000c00943c9bf0000004090bf8c3f0000004076c3c33f000000005249a53f00000080a608b33f00000040fca392bf0000008057e5bbbf00000040e222b43f000000e07d4ebe3f00000020c1e1c3bf00000060c7e7893f000000601b73c43f000000c00bd3adbf000000c06bd4b5bf00000020250fb2bf000000e0d599ce3f000000809076c23f00000020c017c1bf000000a0602bd2bf000000007aadc23f000000a086e5c6bf000000a0c163c3bf0000002083fea73f00000020f09abdbf000000402b4cc1bf000000806364d2bf000000c0e434a6bf00000040d5f2d43f0000008017dfc03f000000c0c98ac2bf000000c09294b73f000000e0544d9dbf000000808f3383bf00000060e784ab3f000000006463ca3f00000060336aaabf000000007fc2b83f000000a0f6a9b6bf0000004096b59a3f000000806b8cce3f000000c05ba19abf00000080a6bd61bf000000001efdcb3f00000080fc5db63f000000a09b1fb73f00000080a74aa43f000000e00296a13f000000c047f1b3bf000000809e2191bf00000080c2a5c5bf00000060b480aabf000000e0e819843f000000804c2b9fbf00000020d823abbf000000601fddba3f000000e0b6c9bcbf00000080e30ac53f000000e04706a5bf000000c01895713f000000003cd2b7bf00000040c2458d3f0000004064a1b2bf000000c0385798bf000000c0ee7cb93f000000a03e3dc3bf00000060e791ca3f000000c0d176c73f00000060522eb23f000000a0cc5cbb3f000000c0014fc73f000000c0eff2ba3f00000080021e6ebf000000607125a4bf000000403963c0bf000000c0532ba9bf000000e00d04b13f000000e09c8bbdbf000000209306c03f000000c0c507993f, 'uploads/user_105_680d995481818.jpeg', NULL, '2025-04-27 02:41:24'),
(74, 106, 0x000000609215b8bf00000060ac0eb13f00000080d107a33f000000a01b06b7bf000000c0c09abfbf000000c016b978bf000000c0377fb9bf000000804f5fb6bf00000060f213c63f000000c048e7c0bf0000000053cccb3f000000206873b1bf00000000410fcabf000000009965b6bf000000c0f180b4bf000000a0f2b3d53f00000080e1bccfbf000000801627c8bf00000000dde776bf00000060527ea03f000000a05d3db23f00000000a7cf84bf000000e09ef1adbf000000406e45bc3f000000a0523bc4bf000000000fc4d8bf00000060267bb5bf00000060473bb3bf0000002043a3bfbf000000a0d30db3bf0000004053e19cbf000000e00bf5b63f000000803a33c7bf0000004059bc9d3f00000080bdb9b43f0000002034efbf3f00000000d544a73f000000203d8ea5bf000000e06641b93f000000e0d3b2a33f00000020bd10d1bf000000005274a93f000000a038b4c33f000000206d08cb3f000000206df1ca3f000000009cf705bf0000002051e8963f000000c070e1bbbf000000c07cbabd3f000000c0832fcebf000000a020cc94bf00000040618dba3f000000402aebb53f000000802027b73f000000005bd3a13f00000000f25dbdbf00000000d13c903f000000c0db48c43f000000c01febc4bf000000a088659f3f000000e0c321af3f000000001267b9bf000000e0a07aa3bf0000006072cbb2bf000000204995cf3f0000002049f7bf3f00000020e889c8bf000000004884c5bf00000060f5f5b93f00000080174dc6bf000000606c83b9bf00000080383285bf00000000107fbcbf00000080bdf6c7bf000000801a07d6bf000000608dd2a8bf00000060791ed63f00000060d625bf3f000000c063aad3bf000000c0b270a73f000000e06365813f000000600295913f000000403043b53f0000008063dfc93f000000002ba9ad3f00000040fcf7943f000000006a9db6bf000000e099bca8bf000000009a8fcf3f00000060f7f993bf000000a0833cb73f00000080b9ecd13f00000040e03e9fbf00000000ef35a63f000000002077623f00000040fc50a73f000000e0cbd9b6bf000000208af4a3bf000000a0df90bfbf00000000e557afbf000000c02486bdbf00000040241c633f00000080488ea9bf0000008027e1ae3f000000601952c3bf000000200bdfc03f000000401a3698bf00000040d588983f0000006014cfaabf000000a0281cb23f000000801b66a8bf000000e0ff58b1bf00000040a466bc3f000000a08deac9bf00000000c868c73f000000e05129c63f000000c09fd68b3f000000605456bd3f0000002069fab53f000000e034c0bf3f00000060c257adbf00000080f058aabf000000002968c0bf00000000c4259dbf000000c0d825ab3f0000002098e6bdbf000000a01e35b23f000000e0fd4ca0bf, 'uploads/user_106_680de0195da6c.jpeg', NULL, '2025-04-27 07:43:21'),
(78, 110, 0x0000004078e6b3bf000000c0311bad3f000000a0242b993f000000405bcc8bbf0000000053ae99bf000000209051b7bf00000020be609ebf000000a053cbb3bf000000604daac43f000000401f67bcbf000000603374d33f00000020a41892bf000000a0e1d8c9bf00000020c146c1bf000000a07814acbf00000000faf2be3f0000004076cdc8bf000000e01d3dbabf00000080e70e6ebf000000c027e4a93f000000c0e1cac33f0000004019449e3f000000c00c79a23f000000c0f65d883f00000000cd26c0bf00000040cfa8d6bf00000060bd3fc3bf00000000d98f583f000000801f08a03f000000a0a327a8bf00000000a16b68bf000000609644b53f0000004018dcccbf0000004009edb3bf00000080895a913f000000c0e0b47a3f000000006f6962bf000000805488b0bf00000000d8ecc73f0000008096b09b3f000000c092b6ccbf000000c0df48acbf00000080f732953f0000008079aecd3f0000004030dec03f0000006021819f3f00000000d8003b3f00000000bf10babf000000801264ad3f000000007e69cbbf00000060c6749b3f000000607545c13f00000020dea2b83f000000c046b0943f0000000096de4d3f00000080756bb5bf000000000434573f000000c03995c23f00000080647bc7bf00000000eb51743f0000000008fd283f000000c05569c0bf000000e0746da2bf00000000d6e5a4bf0000000017aec53f00000000fbb8b33f000000c0e2a3bbbf000000805398c9bf000000c02bb6c33f000000207529c1bf00000000e141b3bf000000e01bf2bf3f000000a0e7f3b3bf000000407d3eccbf00000000066ed5bf0000006062e6a53f000000603979d93f0000008004c1b33f000000408c21c3bf00000040973fb63f000000e03dfe96bf0000002051cbabbf00000000d4d9b73f00000080d1e4c33f00000060ff98abbf00000080b64ab13f000000e093bbc1bf00000000af56a33f00000020bb47c93f00000060973c9ebf00000040e0d6b8bf000000a0c2cdcd3f00000040acdea2bf00000060cf38b93f000000c06cf5b13f000000e01815a83f00000060270aa2bf000000601144a23f000000a03efebdbf000000002fe661bf0000002008bfc03f000000c0ab1e74bf00000060d4eda8bf00000080283cc43f0000004079e3aebf000000804090b73f0000008003d898bf0000000013c199bf000000c01af5a43f000000c02799b0bf000000a02f3ab7bf0000006084b1b5bf000000602cc29f3f000000c05b48cfbf000000207c2ec43f00000080bb65b93f00000060700fa43f00000020263ac33f000000a06839b93f00000040f3d2ac3f00000040adde843f00000060b31baebf000000e04abcc9bf00000080382da8bf000000c0e15cbf3f000000c0640395bf000000804ce8bc3f00000000d354b2bf, 'uploads/user_110_680de8e29a5f2.jpeg', NULL, '2025-04-27 08:20:50'),
(80, 112, 0x00000020ba5ebbbf00000080087fc73f00000040cc56a93f00000000d04c153f000000601439b4bf000000a02c3ba73f000000a0b652b2bf000000606794a5bf00000040fec0a73f000000c02422993f00000000aaedcc3f00000020b6e0afbf000000c0286fd3bf00000000dc7d503f00000040d66aa03f000000202a91c03f00000000a5ebbfbf00000020128691bf000000a0ea15cabf000000006b25b2bf000000206461923f000000000849a23f000000004020b23f0000004011e77cbf000000608d89c2bf000000e0372bd1bf000000c0ceaaa4bf000000e0529bc2bf00000080d164aebf000000e03fe1babf00000040df89b63f0000000016c79dbf00000080819acabf000000a01a23b2bf000000c0fc57a4bf000000201a0891bf0000004067aeb0bf00000000f3aca1bf0000002077d7c33f000000c0f40a72bf000000801081c2bf000000603d5bb13f000000e07aad953f000000608efdcf3f000000000001d23f000000c01686a33f00000000bfe6773f000000c0cb85abbf000000a084b9bf3f000000001e76ccbf00000020fbf19e3f000000c00d4bb53f000000a03179c93f000000601ebeb63f00000080c122b23f000000809b27a9bf000000401718a03f000000c0b4b0c83f00000020f523cabf00000020e651b53f00000040a34a9ebf000000c0139dafbf000000206d72a63f000000404f09adbf000000a0e914c53f000000c0e60cba3f000000801c33a7bf000000407844bcbf00000060b592ca3f000000a01d56bcbf000000e02cd8b9bf000000e0c95db93f000000a05907c1bf000000a07bbfc8bf000000208732d7bf00000000be4e803f00000040eafad03f000000e06df5ad3f000000607a37d0bf000000605ff8b8bf000000a0e9b18ebf000000606aa2aabf000000a0573ca4bf000000e0c5ebb03f000000e0e02ea6bf0000004025b1c4bf000000401363a4bf00000080dc9d62bf000000607784d33f00000080396ac0bf000000c010eda5bf000000a069d0d13f000000400d7fa13f00000040b328bfbf000000a0b1b6953f000000a027bca43f000000602bedb1bf00000000325ba5bf000000e0b970bebf000000c0f6c79ebf000000003b0a92bf000000608ec1c2bf000000e04cc9aabf00000080fe3cb73f000000008940cbbf000000a03b4ebe3f000000405dc687bf00000080f16d99bf000000c0c23ba5bf00000020d41b81bf000000403558afbf000000a036fa9abf000000c03b7bd03f0000008093ccc6bf00000020d59acd3f00000000426bcd3f00000060de3a9bbf00000020d5aea33f000000601b0991bf000000e06434b53f00000040215193bf00000020a203b13f0000008054e3c2bf000000e0ccacc0bf000000c02f7bb03f000000001fcb853f000000401ad0a2bf000000a0ad44b23f, 'uploads/user_112_681197212439c.jpeg', NULL, '2025-04-30 03:21:05');

-- --------------------------------------------------------

--
-- Table structure for table `rfidcards`
--

CREATE TABLE `rfidcards` (
  `card_id` varchar(50) NOT NULL,
  `user_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `issued_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `rfidcards`
--

INSERT INTO `rfidcards` (`card_id`, `user_id`, `is_active`, `issued_at`, `revoked_at`) VALUES
('4c5eea7', 106, 1, '2025-04-30 03:32:22', NULL),
('f3c49fe4', 38, 1, '2025-04-17 03:12:41', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `useraccessview`
-- (See below for the actual view)
--
CREATE TABLE `useraccessview` (
`card_active` tinyint(1)
,`full_name` varchar(101)
,`rfid_card` varchar(50)
,`role` varchar(50)
,`user_active` tinyint(1)
,`user_id` int
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(38, 'Joshua Luis', 'Alboleras', 'albolerasjoshualuis@gmail.com', 'admin123', 'Admin', 1, '2025-04-13 13:10:40', '2025-04-14 02:32:18'),
(105, 'Ruby', 'Tomon', 'ruby@gmail.com', 'admin123', 'Students', 1, '2025-04-27 02:41:24', '2025-04-27 08:43:30'),
(106, 'Jessica', 'Tomon', 'tomon@gmail.com', 'admin123', 'Student', 1, '2025-04-27 07:43:21', '2025-04-27 07:43:21'),
(110, 'Joshua Luis', 'Alboleras', 'albolerasjoshua@gmail.com', 'admin123', 'Student', 1, '2025-04-27 08:20:50', '2025-04-27 08:20:50'),
(112, 'Joe', 'Biden', 'biden@gmail.com', 'admin123', 'Student', 1, '2025-04-30 03:21:05', '2025-04-30 03:21:05');

-- --------------------------------------------------------

--
-- Structure for view `useraccessview`
--
DROP TABLE IF EXISTS `useraccessview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `useraccessview`  AS SELECT `u`.`user_id` AS `user_id`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `full_name`, `u`.`role` AS `role`, `r`.`card_id` AS `rfid_card`, `r`.`is_active` AS `card_active`, `u`.`is_active` AS `user_active` FROM (`users` `u` left join `rfidcards` `r` on((`u`.`user_id` = `r`.`user_id`))) WHERE (`u`.`is_active` = true)  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accesslogs`
--
ALTER TABLE `accesslogs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `device_id` (`device_id`),
  ADD KEY `idx_access_logs_timestamp` (`timestamp`),
  ADD KEY `idx_access_logs_user_id` (`user_id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`device_id`);

--
-- Indexes for table `faceencodings`
--
ALTER TABLE `faceencodings`
  ADD PRIMARY KEY (`encoding_id`),
  ADD KEY `idx_face_encodings_user_id` (`user_id`);

--
-- Indexes for table `rfidcards`
--
ALTER TABLE `rfidcards`
  ADD PRIMARY KEY (`card_id`),
  ADD KEY `idx_rfidcards_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accesslogs`
--
ALTER TABLE `accesslogs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1013;

--
-- AUTO_INCREMENT for table `faceencodings`
--
ALTER TABLE `faceencodings`
  MODIFY `encoding_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accesslogs`
--
ALTER TABLE `accesslogs`
  ADD CONSTRAINT `accesslogs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `accesslogs_ibfk_2` FOREIGN KEY (`device_id`) REFERENCES `devices` (`device_id`) ON DELETE SET NULL;

--
-- Constraints for table `faceencodings`
--
ALTER TABLE `faceencodings`
  ADD CONSTRAINT `faceencodings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `rfidcards`
--
ALTER TABLE `rfidcards`
  ADD CONSTRAINT `rfidcards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
