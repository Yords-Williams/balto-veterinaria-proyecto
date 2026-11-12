<?php

/** Una conexión fallida debe abortar; jamás habilitar una reinstalación. */
$connection = new PDO(
  sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306', getenv('DB_NAME')),
  getenv('DB_USER'),
  getenv('DB_PASSWORD'),
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$tables = $connection->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
if (!$tables) {
  echo 'no';
}
elseif (in_array('config', $tables, TRUE) && in_array('key_value', $tables, TRUE)) {
  echo 'yes';
}
else {
  fwrite(STDERR, "Base no vacía sin instalación reconocible. Revisa manualmente; no se borrará nada.\n");
  exit(1);
}
