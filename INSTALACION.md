# Códigos SUNAT V2

Aplicación para consultar el catálogo proporcionado, asignar códigos a referencias comerciales y completar archivos XLSX por revisión manual.

## Probar sin servidor

1. Extrae el ZIP completo en una carpeta.
2. Abre LEEME.html o index.html en Chrome o Edge actualizados.
3. Espera a que cargue el catálogo y realiza una búsqueda.

No requiere conexión a internet, API de IA ni dependencias descargadas. El catálogo está en assets/catalog.js. La búsqueda y los Excel se procesan en el equipo. El almacenamiento local depende de que el navegador permita localStorage e IndexedDB. Evita el modo incógnito. Si el navegador restringe los archivos locales, utiliza un servidor web local de confianza.

En modo local, los datos no se sincronizan con otros equipos. Usa Mis productos > Respaldo JSON antes de cambiar de carpeta, navegador o equipo, o de borrar los datos del navegador. El respaldo contiene productos y equivalencias; el catálogo actualizado se respalda conservando su archivo Excel.

## Instalar en PHP y MySQL

Requisitos: PHP 8.1 o superior con PDO y pdo_mysql, MySQL 5.7 o superior o MariaDB compatible, HTTPS para conexión remota. La cuenta de base de datos necesita SELECT, INSERT, UPDATE y DELETE sobre las tablas de la aplicación. La importación del esquema necesita CREATE y debe realizarla el administrador.

1. Crea una base de datos exclusiva, por ejemplo codigo_claro, y un usuario con acceso a ella.
2. Importa server/schema.sql mediante phpMyAdmin o el cliente MySQL.
3. Copia server/config.example.php como server/config.php.
4. Completa dsn, db_user, db_password y username.
5. Genera un hash de tu contraseña con PHP y colócalo en password_hash. No escribas la contraseña en texto plano en ese campo. En una terminal de tu equipo puedes ejecutar:

   ```sh
   php -r 'echo password_hash(readline("Contraseña: "), PASSWORD_DEFAULT), PHP_EOL;'
   ```

   El comando solicita la contraseña de forma visible en esa terminal. Ejecútalo en un equipo privado y no compartas su pantalla.

6. Sube index.html, assets/, server/ y .htaccess al directorio de la aplicación. El directorio server debe ejecutar PHP; nunca lo sirvas como texto estático. No es necesario subir tests, herramientas ni documentación.
7. Mantén require_https en true. Solo para pruebas en localhost por HTTP puedes usar false. La detección HTTPS usa la variable HTTPS del servidor: si hay un proxy inverso, configura esa variable en el servidor de origen.
8. Abre la aplicación y pulsa Conectar servidor. Usa el usuario y contraseña configurados.

Para una prueba local con PHP instalado:

```sh
php -S 127.0.0.1:8080
```

Ejecuta el comando desde la carpeta del proyecto. Esta modalidad es para desarrollo local, no para publicación.

### Protección de archivos

Apache debe permitir los .htaccess incluidos y tener mod_authz_core. En Nginx configura la ejecución PHP y bloquea explícitamente el acceso HTTP a server/config.php, server/config.example.php, server/schema.sql y server/catalog_codes.json. Deshabilita listados de directorios. No subas archivos de configuración de respaldo ni credenciales a repositorios públicos.

El catálogo no contiene información de tus productos comerciales y puede consultarse sin iniciar sesión. Los productos y equivalencias del servidor requieren sesión. La V2 utiliza una cuenta de administrador configurada; no incluye registro público, recuperación de contraseña ni roles.

### Datos del servidor y datos locales

Al conectar, la pantalla muestra los datos del servidor. Los datos locales permanecen separados. Para trasladarlos, descarga un respaldo en modo local, inicia sesión y usa Restaurar respaldo. Esta acción reemplaza los productos y equivalencias de destino y pide confirmación. Exporta antes un respaldo del destino si contiene datos que deseas conservar.

Se usan consultas parametrizadas, hash de contraseña, cookies HttpOnly y SameSite Strict, token CSRF, expiración por inactividad y bloqueo temporal tras diez intentos fallidos desde una dirección. La actualización de estado usa una revisión para detectar cambios concurrentes. Ante un conflicto, descarga un respaldo y vuelve a iniciar sesión para cargar los datos vigentes antes de guardar. No se fusionan automáticamente cambios de varios equipos.

## Uso del Excel

- Solo archivos .xlsx sin contraseña. No se admiten .xls, .xlsm o CSV como entrada.
- Máximo 25 MB de entrada, 100 MB descomprimidos y 2.000 referencias por lote.
- Selecciona la hoja, la fila de encabezados y las columnas. La plantilla original usa encabezados en la fila 4.
- La referencia se toma de la columna seleccionada: incluye ahí el tipo, material o uso. La V2 no combina automáticamente columnas de marca y características.
- Los nombres vacíos se omiten; las otras filas y hojas permanecen.
- Los códigos existentes, válidos o desconocidos, se conservan. Una fórmula en la columna del código no se reemplaza.
- Elegir código modifica la sugerencia; después debes confirmar la fila.
- Solo Confirmado se exporta. Deshacer vuelve la fila al estado por revisar.
- Si no hay columna de código, se añade una al final al exportar el primer código confirmado.
- El exportador modifica las celdas confirmadas en la hoja seleccionada y conserva las demás entradas del archivo. No recalcula fórmulas, por lo que Excel puede recalcular al abrirlo. No modifica tablas estructuradas para incluir una nueva columna: selecciona una columna de código existente si debe formar parte de una tabla.
- Utiliza una columna de código de celdas simples, sin combinaciones. No se admite escribir dentro de rangos combinados.
- Los productos confirmados en un lote quedan en el Excel exportado; no se añaden a Mis productos ni a Equivalencias automáticamente. El lote permanece solo durante la sesión de la página; expórtalo antes de recargar.

## Actualizar catálogo

En Catálogo puedes importar un Excel con las hojas grupos, segmentos, familias, clases y productosSUNAT y la misma estructura del archivo original. Se verifican longitudes, duplicados, descripciones y relaciones antes de activar la nueva base en ese navegador. Los códigos ya guardados permanecen y se señalan si no existen en la base nueva.

Para distribuir un catálogo actualizado y mantener la validación del servidor, ejecuta la herramienta incluida desde la carpeta del proyecto con Python y openpyxl disponibles:

```sh
python tools/import_catalog.py "ruta/al/catalogo.xlsx"
```

Esta herramienta reemplaza assets/catalog.js y server/catalog_codes.json solo después de validar el archivo. Respalda ambos archivos antes de actualizar. Sube ambos al servidor. Los navegadores que hayan importado un catálogo local deben importar el nuevo Excel o borrar exclusivamente el almacén IndexedDB codigo-claro-catalog. El respaldo de productos utiliza localStorage y debe conservarse.

## Alcance

- Búsqueda por código exacto, descripción, palabras y variaciones de una edición.
- Filtro de segmento y categorías visibles.
- Equivalencias exactas normalizadas y reglas iniciales para leche en polvo, evaporada/condensada/UHT y fresca.
- Las sugerencias son candidatos revisables, no probabilidades de acierto ni validaciones oficiales.
- Una marca aislada o una descripción incompleta puede requerir más información. No hay reconocimiento universal de marcas o códigos de barras.
- La fecha del archivo recibido no demuestra vigencia oficial del catálogo.
- No hay integración directa con facturación SUNAT, emisión de comprobantes ni conexión a un ERP.

## Archivos

- index.html y assets/: aplicación y catálogo.
- server/api.php: sesiones y persistencia MySQL.
- server/config.example.php: configuración sin credenciales reales.
- server/schema.sql: esquema de base de datos.
- tools/import_catalog.py: regeneración validada del catálogo.
- tests/search.test.cjs: comprobaciones ejecutables con Node.js.
- PRUEBAS.md: evidencia de validación y pendientes.

Esta entrega es un paquete instalable. No se ha desplegado en un hosting ni se ha creado una base de datos remota.

## Novedades V2

- Nombre visible: Códigos SUNAT.
- Singular/plural con variantes españolas presentes en el catálogo: aceite/aceites, motor/motores, lápiz/lápices. No se afirma reconocimiento lingüístico universal.
- Búsqueda mientras escribes, sin necesidad de pulsar Buscar.
- Filtros opcionales desplegables: grupo, segmento, familia y clase. Elegir una familia o clase completa sus categorías superiores. Cambiar un nivel superior limpia los inferiores.
- Los filtros activos aparecen sobre los resultados y pueden quitarse.
- Modo relacionado o coincidencia de todas las palabras importantes (excluye conectores y unidades). El modo estricto puede descartar nombres comerciales que no estén en la descripción del catálogo.
- 30, 60 o 100 resultados por vista, conteo completo y botón Mostrar más.
- Explorar categorías sin escribir texto. Buscar por prefijo numérico de 2 a 7 dígitos o por código completo.
- Tema azul grisáceo con superficies suaves y acento verde petróleo.

Para conservar tus datos de V1, descarga primero un respaldo JSON. V2 mantiene las claves de almacenamiento y acepta los respaldos V1. Si abres V2 desde otra carpeta u origen, restaura el respaldo desde Mis productos.
