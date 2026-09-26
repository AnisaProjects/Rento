<?php
// Flash messages: the one component for success / error / info notices on public pages.
//   echo flash_message('error', $error);        show a message now
//   flash_set('success', 'Saved'); redirect;    show it on the next page view
//   echo flash_render();                        print (and clear) queued messages

const FLASH_LABELS = ['success' => 'SUCCESS', 'error' => 'ERROR', 'warning' => 'NOTE', 'info' => 'INFO'];

// Markup for one message; empty string when there is no message.
function flash_message(string $type, ?string $message, bool $allowHtml = false): string
{
    if ($message === null || $message === '') {
        return '';
    }
    $type = isset(FLASH_LABELS[$type]) ? $type : 'info';
    $role = $type === 'error' ? 'alert' : 'status';
    $body = $allowHtml ? $message : htmlentities($message);
    return '<div class="rento-flash rento-flash--' . $type . '" role="' . $role . '"><strong>'
        . FLASH_LABELS[$type] . '</strong>: ' . $body . '</div>';
}

// Queues a message for the next page view (post/redirect/get).
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $message]) {
        $html .= flash_message($type, $message);
    }
    unset($_SESSION['flash']);
    return $html;
}
?>
