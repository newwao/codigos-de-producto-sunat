<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
if (!is_file(__DIR__ . '/config.php')) respond(['error' => 'Servidor sin configurar. Sigue INSTALACION.md.'], 503);
try {
    $config = require __DIR__ . '/config.php';
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (($config['require_https'] ?? true) && !$https) respond(['error' => 'La conexión al servidor requiere HTTPS.'], 403);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('codigo_claro');
    session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Strict', 'path' => '/']);
    session_start();
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 3600) $_SESSION = [];
    $action = $_GET['action'] ?? 'session';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($action, ['login','save','logout'], true) && $method !== 'POST') respond(['error' => 'Método no permitido.'], 405);
    if (!in_array($action, ['session','login','state','save','logout'], true)) respond(['error' => 'Acción desconocida.'], 404);
    $input = [];
    if ($method === 'POST') {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 5 * 1024 * 1024) respond(['error'=>'La carga supera 5 MB.'],413);
        $raw = file_get_contents('php://input', false, null, 0, 5 * 1024 * 1024 + 1);
        if (strlen($raw) > 5 * 1024 * 1024) respond(['error'=>'La carga supera 5 MB.'],413);
        $input = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($input)) respond(['error'=>'Solicitud inválida.'],400);
        // Same-origin browser requests only; no permissive CORS headers.
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $originHost = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
            $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
            if ($originHost !== $host) respond(['error'=>'Origen no permitido.'],403);
        }
    }
    if ($action === 'session') {
        $auth = isset($_SESSION['username']);
        respond(['authenticated'=>$auth, 'username'=>$_SESSION['username']??'', 'csrf'=>$auth?$_SESSION['csrf']:'']);
    }
    $db = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($action === 'login') {
        $username = (string)($input['username'] ?? '');
        $password = (string)($input['password'] ?? '');
        if (strlen($username)>100 || strlen($password)>1024) respond(['error'=>'Credenciales inválidas.'],400);
        $bucket = hash('sha256', ($_SERVER['REMOTE_ADDR']??'') . '|login');
        $stmt=$db->prepare('INSERT IGNORE INTO login_limits(bucket,failures,window_start) VALUES (?,0,?)');$stmt->execute([$bucket,time()]);
        $stmt=$db->prepare('UPDATE login_limits SET failures=0, window_start=? WHERE bucket=? AND window_start<?');$stmt->execute([time(),$bucket,time()-900]);
        $stmt=$db->prepare('SELECT failures FROM login_limits WHERE bucket=?');$stmt->execute([$bucket]);
        if ((int)$stmt->fetchColumn()>=10) respond(['error'=>'Demasiados intentos. Intenta nuevamente en 15 minutos.'],429);
        if (!hash_equals((string)$config['username'],$username) || !password_verify($password,(string)$config['password_hash'])) {
            $stmt=$db->prepare('UPDATE login_limits SET failures=failures+1 WHERE bucket=?');$stmt->execute([$bucket]);
            respond(['error'=>'Usuario o contraseña incorrectos.'],401);
        }
        $stmt=$db->prepare('DELETE FROM login_limits WHERE bucket=?');$stmt->execute([$bucket]);
        session_regenerate_id(true);$_SESSION=['username'=>$username,'csrf'=>bin2hex(random_bytes(32)),'last'=>time()];
        respond(['username'=>$username,'csrf'=>$_SESSION['csrf']]);
    }
    if (!isset($_SESSION['username'])) respond(['error'=>'Inicia sesión para acceder al servidor.'],401);
    $_SESSION['last']=time();$username=$_SESSION['username'];
    if ($method==='POST' && !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN']??'')) respond(['error'=>'Sesión no válida. Vuelve a iniciar sesión.'],403);
    if ($action==='logout') {$_SESSION=[];session_destroy();respond(['ok'=>true]);}
    if ($action==='state') {
        $stmt=$db->prepare('SELECT revision,payload FROM app_state WHERE username=?');$stmt->execute([$username]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) respond(['products'=>[],'equivalences'=>[],'revision'=>0]);
        $payload=json_decode($row['payload'],true,64,JSON_THROW_ON_ERROR);$payload['revision']=(int)$row['revision'];respond($payload);
    }
    if ($action==='save') {
        $products=$input['products']??null;$equivalences=$input['equivalences']??null;$revision=$input['revision']??null;
        if (!is_array($products)||!is_array($equivalences)||!is_int($revision)||$revision<0||count($products)>10000||count($equivalences)>10000) respond(['error'=>'Datos o revisión inválidos.'],400);
        $codes=array_fill_keys(json_decode(file_get_contents(__DIR__.'/catalog_codes.json'),true,512,JSON_THROW_ON_ERROR),true);
        $clean=['products'=>[],'equivalences'=>[]];$ids=[];
        foreach (['products'=>$products,'equivalences'=>$equivalences] as $kind=>$rows) {
            foreach($rows as $row) {
                if (!is_array($row)) respond(['error'=>'Registro inválido.'],400);
                $reference=$row['reference']??null;$code=$row['code']??null;
                if (!is_string($reference)||trim($reference)===''||strlen($reference)>1000||!is_string($code)||!isset($codes[$code])) respond(['error'=>'Referencia o código fuera del catálogo del servidor.'],400);
                $entry=['reference'=>$reference,'code'=>$code,'updated'=>gmdate('c')];
                if ($kind==='products') {
                    $id=$row['id']??'';
                    if (!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,100}$/',$id)||isset($ids[$id])) respond(['error'=>'Identificador de producto inválido o duplicado.'],400);
                    $ids[$id]=true;$entry['id']=$id;
                    foreach(['brand','pack'] as $field) {if(!is_string($row[$field]??'')||strlen($row[$field]??'')>400) respond(['error'=>'Marca o presentación inválida.'],400);$entry[$field]=$row[$field]??'';}
                }
                $clean[$kind][]=$entry;
            }
        }
        $db->beginTransaction();
        $stmt=$db->prepare('INSERT IGNORE INTO app_state(username,revision,payload) VALUES (?,0,?)');$stmt->execute([$username,'{"products":[],"equivalences":[]}']);
        $stmt=$db->prepare('UPDATE app_state SET payload=?,revision=revision+1 WHERE username=? AND revision=?');$stmt->execute([json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$username,$revision]);
        if ($stmt->rowCount()!==1) {$db->rollBack();respond(['error'=>'Otro equipo modificó los datos. Descarga un respaldo y vuelve a iniciar sesión antes de guardar.'],409);}
        $db->commit();respond(['ok'=>true,'revision'=>$revision+1]);
    }
} catch (JsonException $e) {respond(['error'=>'JSON inválido.'],400);
} catch (Throwable $e) {error_log('Codigo Claro: '.$e->getMessage());respond(['error'=>'No se pudo completar la operación. Revisa la configuración y el registro del servidor.'],500);}
