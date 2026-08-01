# Método Kiru — MVP web

Plataforma web para **alumnos, mentores y administradores** que complementa las
sesiones presenciales del Método Kiru con gamificación, seguimiento de hábitos y
(Fase 2) práctica académica con IA.

Stack: **PHP 8 + MySQL/MariaDB** (pensado para **XAMPP**). Sin dependencias externas.

---

## 🚀 Puesta en marcha en XAMPP (local)

1. Copia esta carpeta dentro de `htdocs`, por ejemplo:
   `C:\xampp\htdocs\metodokiru` (Windows) o `/opt/lampp/htdocs/metodokiru` (Linux).
2. Arranca **Apache** y **MySQL** desde el panel de XAMPP.
3. Abre en el navegador: **http://localhost/metodokiru/install.php**
   Esto crea la base de datos, las tablas y datos de ejemplo.
4. Entra en **http://localhost/metodokiru/** con una de las cuentas de abajo.
5. (Recomendado) borra `install.php` cuando termines.

> Si tu carpeta no se llama `metodokiru`, ajusta `BASE_URL` en `config/config.php`.
> Si tu MySQL usa contraseña, cámbiala también ahí (`DB_PASS`).

### Cuentas de ejemplo

| Rol     | Usuario  | Contraseña  |
|---------|----------|-------------|
| Admin   | `admin`  | `admin123`  |
| Mentor  | `mentor` | `mentor123` |
| Alumno  | `alumno` | `alumno123` |
| Alumno  | `mara`   | `mara123`   |
| Alumno  | `izan`   | `izan123`   |

---

## ✅ Qué incluye (Fase 1)

**Alumno**
- Inicio de sesión (usuario/contraseña) → panel principal.
- Pantalla principal: avatar, nombre, edad, mentor, nivel, SkillCoins totales y de la semana.
- **Fondo según el nivel** (Explorador→Bosque, Constructor→Ciudad, Líder→Montaña, Maestro→Cielo). Cambia solo al subir de nivel.
- Recordatorio diario (editable por el mentor).
- Objetivos (con prioridad, fecha, recompensa y SkillCoins) y hábitos, marcables por el alumno.
- Pantalla de SkillCoins (total, semana, mes, faltan para el premio, histórico).
- Estadísticas con gráficos (SkillCoins por semana/mes, racha, hábitos, objetivos).
- Recompensas con barra de progreso.
- **Tareas de inglés**: lista de tareas creadas por el mentor (una por destreza evaluable:
  vocabulario, gramática, comprensión, escucha y escritura), cada una con título, fecha de
  entrega y estado (pendiente/completada). Los ejercicios son mini-retos tipo Duolingo con
  8 tipos de reto (traducir, completar, ordenar, parejas con cuerdas, escucha, verdadero/falso,
  escribir y emoji), corrección instantánea y mini-lecciones en inglés sencillo.
  Al terminar, el alumno ve su **nota sobre 10** y puede revisar sus respuestas
  (correctas e incorrectas, con la solución). Se generan con IA (Gemini) para que no se
  repitan; sin clave usa un banco local. Cada acierto da 1 SkillCoin (máx. 50/día).
  En móvil se hace a pantalla completa.
- El alumno puede cambiar su animal (avatar) y su nombre de perfil desde la pantalla de inicio.

**Mentor**
- Dashboard con tarjetas de alumnos (nombre, nivel, SkillCoins, última conexión, SkillCoins de la semana).
- Perfil del alumno: editar objetivos, hábitos, mensaje diario, premios, nivel, avatar, ajustar SkillCoins.
- Estadísticas por alumno.
- Notificaciones.

**Administrador**
- Resumen de la plataforma.
- Crear y **eliminar** mentores, alumnos y cuentas de familia; asignar alumnos a mentores.
- Editar niveles/mundos y los umbrales de SkillCoins.

**Familias (rol padre, solo lectura)**
- Progreso de cada hijo: SkillCoins, racha, objetivos, objetivos de recompensa y
  radar de destrezas de inglés.

**Fase 2 (parcial):** Zona académica del mentor: activar materias y **generar teoría/ejercicios con IA**
(requiere una clave gratuita de Google Gemini; ver abajo).

---

## 🗂️ Estructura

```
config/        config.php (credenciales), db.php (conexión PDO)
includes/      functions.php, auth, layout, contexto del alumno
alumno/        panel del alumno (inicio, objetivos, skillcoins, estadísticas, recompensas)
mentor/        dashboard, perfil del alumno, zona académica
admin/          resumen, usuarios, niveles
assets/        css/style.css, js/app.js
sql/           metodo_kiru_solo_tablas.sql (para importar en hosting)
install.php    instalador (crea BD + datos de ejemplo)
```

---

## 🌐 Desplegar online

- **[RAILWAY.md](RAILWAY.md)** — *recomendado*. Conectado a GitHub: cada `push` despliega solo, con MySQL incluida. (Crédito gratis y luego ~5 $/mes.)
- **[DEPLOY.md](DEPLOY.md)** — alternativa 100% gratis con InfinityFree (PHP + MySQL por FTP).
