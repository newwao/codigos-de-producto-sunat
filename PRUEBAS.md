# Validación de V2

## Comprobaciones realizadas

- Sintaxis JavaScript de app.js, excel.js y search.js verificada con Node.
- Catálogo original: 49.022 códigos únicos de ocho dígitos y relaciones completas.
- Búsquedas: código exacto, leche en polvo, evaporada, fresca, tildes y mayúsculas.
- Referencias sin coincidencia y marca Gloria aislada.
- Equivalencias confirmadas y rechazo de códigos inexistentes como equivalencia.
- Filtro de segmento y alternativas para una referencia amplia.
- Lectura del XLSX original con el motor de la aplicación y una adaptación de DOM XML en Node.
- Exportación de una copia de prueba con dos códigos confirmados y reapertura con un lector independiente (openpyxl).
- Comparación de las ocho hojas y del contenido ZIP para comprobar que solo cambiaron las celdas confirmadas C5 y C6 de la hoja IMPORTAR.

Las pruebas no equivalen a una revisión visual ni a una ejecución completa en navegador.

## Comprobaciones pendientes de instalación

No se dispone de PHP/MySQL ni de un navegador local ejecutable en el entorno utilizado. El navegador remoto impidió abrir archivos locales por su política de acceso. No se ha ejecutado la API PHP, ni se ha validado su conexión MySQL o realizado una prueba visual interactiva completa.

Antes de usar el servidor con datos de trabajo:

1. Abrir la interfaz en escritorio y móvil y comprobar navegación, diálogos y lectura de textos.
2. Guardar un producto local, recargar y comprobar que sigue disponible.
3. Cargar un XLSX pequeño, confirmar dos filas, exportar y verificar los códigos en Excel.
4. Conectar al servidor, guardar un producto y comprobarlo desde otra sesión.
5. Verificar que credenciales incorrectas y peticiones sin sesión no dan acceso a los datos.
6. Abrir dos sesiones para comprobar el aviso de conflicto al guardar sobre una revisión antigua.
7. Probar respaldo/restauración con datos de prueba y verificar los archivos de configuración protegidos.

Para repetir las pruebas del buscador:

```sh
node tests/search.test.cjs
```

## Verificación adicional V2

Se ejecutaron las pruebas del motor con los 49.022 registros. Aceite y aceites devuelven los mismos 98 códigos candidatos. Motor/motores y lápiz/lápices también devuelven conjuntos equivalentes. Se verificaron los filtros de grupo, segmento, familia y clase antes de limitar resultados; la consulta por prefijo; la navegación de categorías sin texto; y el modo de todas las palabras. La sintaxis JavaScript fue comprobada.

Los cambios visuales de V2 no se han inspeccionado en un navegador ejecutable en este entorno. El procesamiento de Excel y la API PHP no fueron modificados en esta versión.
