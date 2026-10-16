<?php
declare(strict_types=1);

/**
 * View legado — o fluxo de pauta foi unificado em sessao/form.
 * Mantido apenas como segurança caso algum link antigo ainda aponte para cá.
 */
$sessaoId = (int) (($sessao['id'] ?? 0));
$target = $sessaoId > 0
    ? '?r=sessao/form/' . $sessaoId
    : '?r=sessao/index';

header('Location: ' . base_url($target), true, 302);
exit;
