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
	<p class="big_title_rb">Настройки экспорта в РобоМаркет</p>

	<form method="post" action="options.php">
		<?php wp_nonce_field('update-options'); ?>

		<p>
			Укажите в настройке "URL процессинга" следующий адрес:
			<code id="ResultURL"><?php echo site_url('/?robomarket'); ?></code>

			<button class="btn btn-default btn-clipboard btn-main" data-clipboard-target="#ResultURL" onclick="event.preventDefault();">
				Скопировать
			</button>
		</p>

		<p>
			Выберите тип запроса "JSON", тип хеша "MD5" и не забудьте поставить галочку напротив "Использовать N заказа
			в магазине как InvId в ROBOKASSA"
		</p>

		<p>
			Секретная фраза РобоМаркета:
			<input type="password" name="robokassa_payment_robomarket_secret" value="<?php echo get_option('robokassa_payment_robomarket_secret') ?>">
		</p>

		<p>
			Вам необходимо загрузить автоматически сгенерированный каталог вашего магазина в Личном Кабинете на сайте
			Робокассы, в разделе "Панель Robomarket"
		</p>

		<p>
			<img src="<?=\plugin_dir_url(__FILE__);?>/images/robokassa_help.png" class="robomarket-preview-image">
		</p>

		<input type="hidden" name="action" value="update"/>
		<input type="hidden" name="page_options" value="robokassa_payment_robomarket_secret"/>

		<p class="submit">
			<a href="<?php echo admin_url('/admin.php?page=robokassa_payment_YMLGenerator'); ?>" target="_blank" class="button-secondary">Экспортировать</a>

			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
		</p>
	</form>
</div>