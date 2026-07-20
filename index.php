<?php
declare(strict_types=1);

/** Raiz do projeto: sempre encaminha para o front controller em public/. */
header('Location: public/index.php?r=auth/login', true, 302);
exit;
