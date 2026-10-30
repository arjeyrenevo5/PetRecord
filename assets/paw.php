<?php
function paw_svg(string $class = 'paw-icon'): string
{
    return '<svg class="' . $class . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">'
        . '<circle cx="5.6" cy="10.2" r="2.05"/>'
        . '<circle cx="9.7" cy="6.3" r="2.05"/>'
        . '<circle cx="14.3" cy="6.3" r="2.05"/>'
        . '<circle cx="18.4" cy="10.2" r="2.05"/>'
        . '<path d="M12 11.1c2.9 0 5.6 2.2 5.6 5.1 0 2.3-1.8 3.7-3.9 3.7-1.1 0-1.5-.5-1.7-.7-.2-.2-.4-.4-.7-.4s-.5.2-.7.4c-.2.2-.6.7-1.7.7-2.1 0-3.9-1.4-3.9-3.7 0-2.9 2.7-5.1 5.6-5.1Z"/>'
        . '</svg>';
}
