<?php

if(!\current_user_can('activate_plugins'))
{

    echo '<br /><br />
				<div class="error notice">
	                <p>У Вас не хватает прав на настройку компонента</p>
				</div>
			';
    return;
}

?>
<div>

    <script>
        var data = {
            "rk_reg":true,
            "site_url":"<?php echo site_url(); ?>",
            "result_url":"<?php echo site_url('/?robokassa=result'); ?>",
            "success_url":"<?php echo site_url('/?robokassa=success'); ?>",
            "fail_url":"<?php echo site_url('/?robokassa=fail'); ?>",
            "callback_url":"<?php echo site_url('/?robokassa=registration'); ?>"
        };

        function test() {
            document.getElementById('f').contentWindow.postMessage(data, "*");
        }

        //console.log(data);
    </script>
    <p align="center"><iframe onload="test()" id="f" src="https://reg.robokassa.ru/form_register_merch_wp.php" width=1000 height="1000"></iframe></p>

</div>
