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
<div class="content_holder robomarket-settings">
    <p class="big_title_rb">Нажимая кнопку "скачать" будет загружен XML файл со всеми вашими товарами </p>

    <p class="submit">
        <a href="<?php echo admin_url('/admin.php?page=robokassa_payment_YMLGenerator'); ?>" target="_blank" class="button-secondary">Скачать</a>
    </p>
    </form>
</div>