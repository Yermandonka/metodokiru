# Desplegar Método Kiru gratis

La app es **PHP + MySQL**, así que necesitas un hosting que ofrezca ambos.
La opción gratuita más sencilla y sin tarjeta de crédito es **InfinityFree**.

> Alternativas equivalentes: **000webhost**, **AwardSpace**, **ByetHost**.
> Plataformas tipo Vercel/Netlify **no** sirven aquí porque no ejecutan PHP + MySQL.

---

## Opción recomendada: InfinityFree (100% gratis)

### 1. Crea la cuenta y el hosting
1. Entra en **https://infinityfree.com** → *Sign Up* (solo email).
2. *Create Account* → elige un subdominio gratis, p. ej. `metodokiru.rf.gd`
   (o conecta tu propio dominio si tienes uno).
3. Espera a que el hosting quede activo (unos minutos).

### 2. Crea la base de datos MySQL
1. En el **vPanel** del hosting, abre **MySQL Databases**.
2. Crea una base de datos. Apunta los datos que te da (ejemplo):
   - **Host:** `sqlXXX.infinityfree.com`
   - **Nombre BD:** `epiz_12345_kiru`
   - **Usuario:** `epiz_12345`
   - **Contraseña:** la de tu cuenta

### 3. Ajusta la configuración del proyecto
Edita `config/config.php` con los datos anteriores:

```php
define('DB_HOST', 'sqlXXX.infinityfree.com'); // el host que te dieron
define('DB_USER', 'epiz_12345');
define('DB_PASS', 'TU_CONTRASEÑA');
define('DB_NAME', 'epiz_12345_kiru');

// La app irá en la raíz del dominio, así que BASE_URL vacío:
define('BASE_URL', '');
```

### 4. Sube los archivos
1. En el vPanel abre **Online File Manager** (o usa **FileZilla** por FTP con los datos de *FTP Accounts*).
2. Entra en la carpeta **`htdocs`** del servidor.
3. Sube **todo el contenido** del proyecto dentro de `htdocs`
   (que `index.php` quede en `htdocs/index.php`).

### 5. Importa la base de datos
1. En el vPanel abre **phpMyAdmin** y selecciona tu base de datos.
2. Pestaña **Importar** → sube el archivo **`sql/metodo_kiru_solo_tablas.sql`** → *Continuar*.
   (Esto crea las tablas y los datos de ejemplo, incluidas las cuentas de acceso.)

> Alternativa: en lugar de importar, visita `https://TU-DOMINIO/install.php` una vez.
> Funciona igual, pero recuerda **borrar `install.php`** después.

### 6. Entra
Abre `https://TU-DOMINIO/` y accede con `admin / admin123` (cámbialo después).

---

## Notas importantes

- **Seguridad:** cambia las contraseñas de ejemplo y borra `install.php` tras instalar.
- **IA (Fase 2):** el hosting gratuito de InfinityFree suele **bloquear conexiones salientes**,
  por lo que la generación con Gemini puede no funcionar en el plan gratis.
  Todo lo demás (Fase 1) funciona con normalidad.
- **HTTPS:** InfinityFree ofrece SSL gratis; puedes activarlo desde el panel.

---

## Alternativa técnica (más control): Railway / Render

Si prefieres una plataforma moderna con Git, puedes desplegar PHP + MySQL con Docker.
Es más potente pero requiere más configuración; para un MVP, **InfinityFree es más rápido**.
Si quieres esta vía, avísame y te preparo el `Dockerfile` y el `docker-compose`.
