# Desplegar Método Kiru en Railway (conectado a GitHub)

Railway despliega **directamente desde este repositorio**: cada `git push` a `main`
vuelve a desplegar solo. Incluye MySQL en el mismo proyecto. Ya está todo preparado
(`Dockerfile` + `docker/start.sh`); solo tienes que seguir estos pasos en la web de Railway.

---

## 1. Crea el proyecto desde GitHub
1. Entra en **https://railway.com** e inicia sesión con **GitHub**.
2. **New Project → Deploy from GitHub repo**.
3. Autoriza a Railway y elige el repo **`Yermandonka/metodokiru`**.
4. Railway detecta el `Dockerfile` y empieza a construir la app automáticamente.

## 2. Añade la base de datos MySQL
1. Dentro del proyecto: **+ New → Database → Add MySQL**.
2. Espera a que la base de datos quede lista (unos segundos).

## 3. Conecta la base de datos con la app
1. Abre el servicio de la **app** (el que viene del repo) → pestaña **Variables**.
2. **+ New Variable → Add Reference** (referencia a otra variable).
3. Elige el servicio **MySQL** y la variable **`MYSQL_URL`**.
   - El nombre de la variable en la app debe quedar exactamente **`MYSQL_URL`**.
4. Guarda. Railway volverá a desplegar la app con la conexión ya inyectada.

> `config.php` detecta `MYSQL_URL` y se conecta solo. No hace falta tocar código.

## 4. Genera el dominio público
1. En el servicio de la app → **Settings → Networking → Generate Domain**.
2. Obtendrás una URL tipo `https://metodokiru-production.up.railway.app`.

## 5. Crea las tablas y los datos
1. Cuando el despliegue esté en verde, visita una vez:
   **`https://TU-DOMINIO/install.php`**
   Esto crea las tablas y los datos de ejemplo en la MySQL de Railway.
2. Entra en `https://TU-DOMINIO/` con **`admin` / `admin123`**.
3. Cambia las contraseñas de ejemplo.

---

## A partir de ahora
Cada cambio se despliega solo:
```
git add .
git commit -m "mi cambio"
git push
```
Railway detecta el push y actualiza la web automáticamente. ✅

## Notas
- **IA (Fase 2):** para activarla, añade en Railway una variable
  `GEMINI_API_KEY` con tu clave gratuita de Google Gemini (Variables → New Variable).
- **Coste:** Railway da un crédito de prueba gratuito; después el plan Hobby
  ronda los 5 $/mes según uso. El desarrollo local en XAMPP sigue siendo gratis.
- **Local:** en tu XAMPP todo sigue igual (`config.php` detecta `localhost`).
