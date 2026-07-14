
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `alumnos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alumnos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `mentor_id` int(11) DEFAULT NULL,
  `nivel_id` int(11) DEFAULT NULL,
  `avatar` varchar(40) DEFAULT 'zorro',
  `mensaje_diario` text DEFAULT NULL,
  `tiempo_maximo` int(11) DEFAULT 30,
  `ultima_conexion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `alumnos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `alumnos` WRITE;
/*!40000 ALTER TABLE `alumnos` DISABLE KEYS */;
INSERT INTO `alumnos` VALUES (1,3,12,2,NULL,'zorro','Hoy recuerda preparar la mochila antes de dormir.',30,NULL),(2,4,11,2,NULL,'zorro','Solo te faltan unas monedas para tu recompensa.',30,NULL),(3,5,13,2,NULL,'zorro','Esta semana tu objetivo es llegar puntual todos los días.',30,NULL);
/*!40000 ALTER TABLE `alumnos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `conexiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `conexiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `alumno_id` (`alumno_id`,`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `conexiones` WRITE;
/*!40000 ALTER TABLE `conexiones` DISABLE KEYS */;
INSERT INTO `conexiones` VALUES (5,1,'2026-07-10'),(4,1,'2026-07-11'),(3,1,'2026-07-12'),(2,1,'2026-07-13'),(1,1,'2026-07-14');
/*!40000 ALTER TABLE `conexiones` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `habitos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `habitos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `completado` tinyint(1) DEFAULT 0,
  `fecha` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `habitos` WRITE;
/*!40000 ALTER TABLE `habitos` DISABLE KEYS */;
INSERT INTO `habitos` VALUES (1,1,'Beber agua',1,'2026-07-14'),(2,1,'Hacer la cama',0,'2026-07-14'),(3,1,'Estudiar 20 min',0,'2026-07-14');
/*!40000 ALTER TABLE `habitos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `materias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activa` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `materias` WRITE;
/*!40000 ALTER TABLE `materias` DISABLE KEYS */;
INSERT INTO `materias` VALUES (1,1,'Matemáticas',1),(2,1,'Inglés',1),(3,1,'Lengua',0),(4,1,'Ciencias',0);
/*!40000 ALTER TABLE `materias` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `niveles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `niveles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `orden` int(11) NOT NULL,
  `skillcoins_necesarias` int(11) NOT NULL DEFAULT 0,
  `mundo` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `niveles` WRITE;
/*!40000 ALTER TABLE `niveles` DISABLE KEYS */;
INSERT INTO `niveles` VALUES (1,'Explorador',1,0,'bosque'),(2,'Constructor',2,100,'ciudad'),(3,'Líder',3,300,'montana'),(4,'Maestro',4,600,'cielo');
/*!40000 ALTER TABLE `niveles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mentor_id` int(11) NOT NULL,
  `alumno_id` int(11) DEFAULT NULL,
  `mensaje` varchar(255) NOT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `creada_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` VALUES (1,2,3,'Izan no entra desde hace 5 días.',0,'2026-07-14 02:59:47'),(2,2,1,'Leo ha superado un objetivo.',0,'2026-07-14 02:59:47');
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `objetivos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `objetivos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `prioridad` enum('baja','media','alta') DEFAULT 'media',
  `fecha` date DEFAULT NULL,
  `recompensa` varchar(255) DEFAULT NULL,
  `skillcoins` int(11) DEFAULT 0,
  `completado` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `objetivos` WRITE;
/*!40000 ALTER TABLE `objetivos` DISABLE KEYS */;
INSERT INTO `objetivos` VALUES (1,1,'Preparar mochila','Dejarla lista antes de dormir','alta','2026-07-14','Tiempo de juego',10,1),(2,1,'Leer 15 minutos','Un capítulo del libro de aventuras','media','2026-07-14','',15,0),(3,1,'Revisar agenda','Comprobar deberes del día','baja','2026-07-14','',5,0);
/*!40000 ALTER TABLE `objetivos` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `recompensas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recompensas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `costo` int(11) NOT NULL,
  `activa` tinyint(1) DEFAULT 1,
  `conseguida` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `recompensas` WRITE;
/*!40000 ALTER TABLE `recompensas` DISABLE KEYS */;
INSERT INTO `recompensas` VALUES (1,1,'Ir al cine',155,1,0),(2,2,'Nueva libreta Kiru',100,1,0),(3,3,'Excursión especial',400,1,0);
/*!40000 ALTER TABLE `recompensas` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `skillcoins_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `skillcoins_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `alumno_id` (`alumno_id`,`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `skillcoins_log` WRITE;
/*!40000 ALTER TABLE `skillcoins_log` DISABLE KEYS */;
INSERT INTO `skillcoins_log` VALUES (1,1,30,'Objetivo cumplido','2026-06-19 02:59:47'),(2,1,20,'Hábito: leer','2026-06-26 02:59:47'),(3,1,25,'Puntualidad','2026-07-02 02:59:47'),(4,1,15,'Objetivo cumplido','2026-07-08 02:59:47'),(5,1,20,'Racha','2026-07-11 02:59:47'),(6,1,15,'Hábito','2026-07-13 02:59:47'),(7,1,10,'Entrada diaria','2026-07-14 02:59:47'),(8,2,40,'Objetivos','2026-07-04 02:59:47'),(9,2,30,'Hábitos','2026-07-10 02:59:47'),(10,2,15,'Racha','2026-07-13 02:59:47'),(11,3,120,'Reto mensual','2026-06-24 02:59:47'),(12,3,90,'Objetivos','2026-07-05 02:59:47'),(13,3,130,'Constancia','2026-07-12 02:59:47');
/*!40000 ALTER TABLE `skillcoins_log` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `uso_diario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `uso_diario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alumno_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `minutos` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_alumno_fecha` (`alumno_id`,`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `uso_diario` WRITE;
/*!40000 ALTER TABLE `uso_diario` DISABLE KEYS */;
INSERT INTO `uso_diario` VALUES (1,1,'2026-07-14',15),(2,1,'2026-07-13',18),(3,1,'2026-07-12',21),(4,1,'2026-07-11',24),(5,1,'2026-07-10',27);
/*!40000 ALTER TABLE `uso_diario` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('alumno','mentor','admin') NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'admin','$2y$10$sazakjzMvUoLTdA6s8Kk.Oc5gNnTaey2DoVPD9JV6vQsCAuk0XXWW','admin','Administrador','2026-07-14 00:59:47'),(2,'mentor','$2y$10$X1xMp.lxQBi7uLuYOI0weO7ZBNiEbMsm7TJYJKYOMRxYlfQzxYWL2','mentor','Ana (Mentora)','2026-07-14 00:59:47'),(3,'alumno','$2y$10$xLrUMWsEb7lPWG4p.JEqNeo/GMIy7h9f3BltHd8TM7G1gPgfn4fyW','alumno','Leo','2026-07-14 00:59:47'),(4,'mara','$2y$10$QX831iNkt3GLHLPZCvtS8eASrEwibk.m0fZcgPw1imdqy4SvcEU3q','alumno','Mara','2026-07-14 00:59:47'),(5,'izan','$2y$10$0ugsdzylgWdLytvsc9QyJejjTDExwfzjIK.gUj3dFZNQAgnnzt/.S','alumno','Izan','2026-07-14 00:59:47');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

