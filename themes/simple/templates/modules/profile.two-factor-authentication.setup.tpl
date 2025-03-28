<script type="text/javascript">
    $("body").on("shown.bs.modal", ".modal", function() {
        $("#adm_tfa_setup_form").find("*").filter(":input:visible:first").focus()
    });
    $("#adm_tfa_setup_form").submit(formSubmit);
</script>

<div class="modal-header">
    <h3 class="modal-title">{$l10n->get('SYS_TFA_SETUP')}</h3>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>
        <b>{$l10n->get('SYS_TFA_SETUP_INSTRUCTIONS')}</b>
        <ol>
            <li>{$l10n->get('SYS_TFA_SETUP_INSTRUCTIONS_STEP_1')}</li>
            <li>{$l10n->get('SYS_TFA_SETUP_INSTRUCTIONS_STEP_2')}</li>
            <li>{$l10n->get('SYS_TFA_SETUP_INSTRUCTIONS_STEP_3')}</li>
        </ol>
        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
        {include 'sys-template-parts/form.custom-content.tpl' data=$elements['qr_code']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['otp_code']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    </form>
</div>
