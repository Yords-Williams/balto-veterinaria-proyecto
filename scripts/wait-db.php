<?php

/** Espera hasta 120 segundos sin imprimir credenciales. */
for ($attempt = 0; $attempt < 60; $attempt++) {
  try {
    $connection = new PDO(
      sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT') ?: '3306', getenv('DB_NAME')),
      getenv('DB_USER'),
      getenv('DB_PASSWORD'),
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2],
    );
    $connection->query('SELECT 1');
    exit(0);
  }
  catch (PDOException) {
    sleep(2);
  }
}
fwrite(STDERR, "MariaDB no respondió a tiempo. Revisa su healthcheck y las variables DB_.\n");
exit(1);
