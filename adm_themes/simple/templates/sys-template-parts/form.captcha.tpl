<div id="captcha_puzzle_group" class="form-control-group mb-2 {$class}">
    <img id="captcha" src="{$ADMIDIO_URL}/adm_program/libs/securimage/securimage_show.php" alt="CAPTCHA Image" />
    <a class="admidio-icon-link" href="javascript:void(0)"
        onclick="document.getElementById('captcha').src='{$ADMIDIO_URL}/adm_program/libs/securimage/securimage_show.php?'
        + Math.random(); return false;">
        <i class="fas fa-sync-alt fa-lg" data-bs-toggle="tooltip" title="{$l10n->get('SYS_RELOAD')}"></i>
    </a>
</div>
