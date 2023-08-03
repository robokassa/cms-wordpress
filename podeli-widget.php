<?php

function podeli_product_widget()
{
    global $product;
    $price = $product->get_price();
    $product_id = $product->get_id();

    setlocale(LC_TIME, 'ru_RU.UTF-8');
    $podeli_2w_date = strftime('%d %b', strtotime('+2 weeks'));
    $podeli_4w_date = strftime('%d %b', strtotime('+4 weeks'));
    $podeli_6w_date = strftime('%d %b', strtotime('+6 weeks'));

    if (get_option('robokassa_payment_podeli_widget_onoff') === 'true' && $price > 300 && $price < 30000) {
        if (get_option('robokassa_podeli_widget_style') == 0) {
            echo '
<div class="wiget-block-wrapper"> <div class="wiget-block">
  <div class="wiget-block__content">
    <div class="wiget-block__logotypes">
      <div class="wiget-block__logotype">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="24" rx="12" fill="FFFFFF"/>
                            <path d="M6,6v12h12L6,6z" fill="#FF5722"/>
                            <path d="M18 6H6V18L18 6Z" fill="#023D5E"/>
                        </svg>
      </div>
      <div class="wiget-block__logotype">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="24" height="24" rx="12" fill="#EE3124" />
          <path d="M12 4C7.57233 4 4 7.5891 4 12C4 16.4109 7.5891 20 12 20C16.4277 20 20 16.4109 20 12C20 7.5891 16.4109 4 12 4ZM12 18.0042C8.69602 18.0042 5.99581 15.3208 5.99581 12C5.99581 8.67925 8.67924 5.99581 12 5.99581C15.3208 5.99581 18.0042 8.67925 18.0042 12C18.0042 15.3208 15.304 18.0042 12 18.0042Z" fill="white" />
          <path d="M11.9414 4.99924C11.9414 5.48561 12.2936 5.92167 12.7968 5.98876C15.4299 6.35773 17.5095 8.43739 17.8785 11.0705C17.9456 11.5569 18.3817 11.9259 18.868 11.9259C19.4718 11.9259 19.9414 11.3892 19.8575 10.7854C19.3544 7.28016 16.5703 4.49609 13.0651 4.00972C12.4613 3.92586 11.9414 4.39546 11.9414 4.99924Z" fill="#1D2939" />
        </svg>
      </div>
    </div>
    <div class="wiget-block__title-subtitle">
      <h5 class="wiget-block__title">
        <span class="wiget-block__months">4</span> платежа по <span class="wiget-block__payment">' . $price / 4 . '</span> ₽
      </h5>
      <p class="wiget-block__subtitle"> Без комиссий и переплат </p>
    </div>
  </div>
  <div class="wiget-block__info">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <g clip-path="url(#clip0_13_106)">
        <path d="M12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2ZM12 20C7.6 20 4 16.4 4 12C4 7.6 7.6 4 12 4C16.4 4 20 7.6 20 12C20 16.4 16.4 20 12 20ZM12 11.5C11.4 11.5 11 11.9 11 12.5V15.5C11 16.1 11.4 16.5 12 16.5C12.6 16.5 13 16.1 13 15.5V12.5C13 11.9 12.6 11.5 12 11.5ZM12 7.5C11.3 7.5 10.8 8.1 10.8 8.7C10.8 9.3 11.3 10 12 10C12.7 10 13.2 9.4 13.2 8.8C13.2 8.2 12.7 7.5 12 7.5Z" fill="#455A64" />
      </g>
      <defs>
        <clipPath id="clip0_13_106">
          <rect width="20" height="20" fill="white" transform="translate(2 2)" />
        </clipPath>
      </defs>
    </svg>
  </div>
  <div class="wiget-block__prompt-wrapper">
    <div class="wiget-block__prompt">
      <p class="wiget-block__prompt-text"> Забирайте покупки за 1/4 стоимости с Подели и Robokassa! Остальное оплачивайте тремя платежами раз в 2 недели. Без комиссий и переплат! </p>
      <div class="wiget-block-split">
        <div class="wiget-block-split__item">
          <p class="split-item-day">Сегодня</p>
          <p class="split-item-payment">' . $price / 4 . ' ₽</p>
        </div>
        <div class="wiget-block-split__item wiget-block-split__item_disable">
          <p class="split-item-day">' . $podeli_2w_date . '</p>
          <p class="split-item-payment">' . $price / 4 . ' ₽</p>
        </div>
        <div class="wiget-block-split__item wiget-block-split__item_disable">
          <p class="split-item-day">' . $podeli_4w_date . '</p>
          <p class="split-item-payment">' . $price / 4 . ' ₽</p>
        </div>
        <div class="wiget-block-split__item wiget-block-split__item_disable">
          <p class="split-item-day">' . $podeli_6w_date . '</p>
          <p class="split-item-payment">' . $price / 4 . ' ₽</p>
        </div>
      </div>
    </div>
  </div>
</div>
';
        } else {
            echo '
<div class="wiget-block-wrapper">
  <div id="openModal" class="wiget-block-v2" id="openModal">
    <div class="wiget-head-v2">
      <div class="wiget-head-v2__text">
        <span class="text__payment"> ' . $price / 4 . ' ₽ </span>
        <span class="text__payment-length"> х 4 платежа </span>
      </div>
      <div class="wiget-head-v2__button"> Оплатить 25% </div>
    </div>
    <div class="wiget-block-split">
      <div class="wiget-block-split__item">
        <p class="split-item-day">Сегодня</p>
        <p class="split-item-payment">' . $price / 4 . ' ₽</p>
      </div>
      <div class="wiget-block-split__item wiget-block-split__item_disable">
        <p class="split-item-day">' . $podeli_2w_date . '</p>
        <p class="split-item-payment">' . $price / 4 . ' ₽</p>
      </div>
      <div class="wiget-block-split__item wiget-block-split__item_disable">
        <p class="split-item-day">' . $podeli_4w_date . '</p>
        <p class="split-item-payment">' . $price / 4 . ' ₽</p>
      </div>
      <div class="wiget-block-split__item wiget-block-split__item_disable">
        <p class="split-item-day">' . $podeli_6w_date . '</p>
        <p class="split-item-payment">' . $price / 4 . ' ₽</p>
      </div>
    </div>
    <div class="modal-wrapper">
      <div class="modal">
        <button id="closeModal" class="modal__close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.6472 8.70065C17.0342 8.30659 17.0284 7.67345 16.6344 7.2865C16.2403 6.89954 15.6072 6.90529 15.2202 7.29935L11.9897 10.5891L8.70065 7.35935C8.30659 6.97239 7.67345 6.97815 7.28649 7.37221C6.89954 7.76627 6.90529 8.3994 7.29935 8.78636L10.5884 12.0161L7.36015 15.3036C6.97319 15.6977 6.97894 16.3308 7.373 16.7178C7.76706 17.1047 8.4002 17.099 8.78716 16.7049L12.0154 13.4174L15.3036 16.6464C15.6977 17.0334 16.3308 17.0276 16.7178 16.6336C17.1047 16.2395 17.099 15.6064 16.7049 15.2194L13.4167 11.9904L16.6472 8.70065Z" fill="#8F95AE" />
          </svg>
        </button>
        <div class="modal-logotypes">
          <div class="robokassa-logo">
                        <svg viewBox="0 0 378.97 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <style type="text/css">
	.st0{fill:#FF5722;}
	.st1{fill:#023D5E;}
</style>
<g>
    <path class="st0" d="M25,25v50h50L25,25z"/>
	<path class="st1" d="M75,25H25v50L75,25z"/>
	<path class="st0" d="M256.98,35c-7.46,0-14,6.04-14,13.49V65h6v-6h15.99v6h6v-16.5C270.97,41.04,264.43,35,256.98,35z M264.97,53h-15.99v-4.49c0-4.14,3.86-7.5,8-7.5c4.14,0,8,3.36,8,7.5L264.97,53L264.97,53z"/>
	<path class="st0" d="M339.96,35c-7.46,0-14,6.04-14,13.49V65h6v-6h15.99v6h6v-16.5C353.96,41.04,347.42,35,339.96,35z M347.96,53h-15.99v-4.49c0-4.14,3.86-7.5,8-7.5c4.14,0,8,3.36,8,7.5L347.96,53L347.96,53z"/>
	<path class="st1" d="M195.98,35c-8.28,0-15,6.71-15,15c0,8.28,6.71,15,15,15c8.28,0,15-6.71,15-15C210.99,41.71,204.26,35,195.98,35z M195.98,58.99c-4.96,0-9-4.03-9-9c0-4.96,4.03-9,9-9c4.96,0,9,4.03,9,9C204.98,54.97,200.95,58.99,195.98,58.99z"/>
	<path class="st1" d="M174.62,48.41c0.74-1.31,1.17-2.81,1.17-4.42c0-4.96-4.03-9-9-9h-11.79v30h14.8c4.96,0,9-4.03,9-9C178.78,52.81,177.12,50.02,174.62,48.41z M160.99,40.99h5.8c1.65,0,3,1.35,3,3c0,1.65-1.35,3-3,3h-5.8V40.99z M169.78,58.99h-8.8v-6h8.8c1.65,0,3,1.35,3,3C172.78,57.63,171.43,58.99,169.78,58.99z"/>
	<path class="st1" d="M136.49,35c-8.28,0-15,6.71-15,15c0,8.28,6.71,15,15,15c8.29,0,15-6.71,15-15C151.49,41.71,144.77,35,136.49,35z M136.49,58.99c-4.96,0-9-4.03-9-9c0-4.96,4.03-9,9-9c4.96,0,9,4.03,9,9C145.49,54.97,141.45,58.99,136.49,58.99z"/>
	<path class="st0" d="M289.94,47.06l0.03-0.06h-7c-1.65,0-3-1.35-3-3c0-1.65,1.35-3,3-3h10.99l4-6h-15c-4.96,0-9,4.03-9,9c0,4.65,3.52,8.47,8.03,8.94L281.97,53h7c1.65,0,3,1.35,3,3c0,1.65-1.35,3-3,3h-10.99l-4,6h15c4.96,0,9-4.03,9-9C297.97,51.35,294.45,47.53,289.94,47.06z"/>
	<path class="st0" d="M307.96,35c-4.96,0-9,4.03-9,9c0,4.65,3.52,8.47,8.03,8.94L306.96,53h7c1.65,0,3,1.35,3,3c0,1.65-1.35,3-3,3h-10.99l-4,6h15c4.96,0,9-4.03,9-9c0-4.65-3.52-8.47-8.03-8.94l0.03-0.06h-7c-1.65,0-3-1.35-3-3c0-1.65,1.35-3,3-3h10.99l4-6L307.96,35z"/>
	<path class="st0" d="M239.67,35L227.4,48.06l12.57,16.92h-7l-9.57-12.69l-2.43,2.9v9.78h-6V35h6v11.83L231.8,35L239.67,35L239.67,35z"/>
	<path class="st1" d="M101,65h-6V35h14.13c2.53,0,4.69,0.86,6.52,2.59c1.86,1.76,3.31,4.34,3.35,6.9c0.02,2.32-0.53,4.21-2.01,6c-1.4,1.73-2.83,3.04-5,3.5l7.5,10.99h-7l-7.05-10.38c-0.28-0.39-0.73-0.61-1.21-0.61h-3.23L101,65L101,65z M101,48.49h8c2.21,0,4-1.79,4-4c0-2.21-1.79-4-4-4h-8V48.49z"/>
		</g>
            </svg>
          </div>
          <span class="separate-logo"></span>
          <div class="podeli-logo">
            <svg width="72" height="12" viewBox="0 0 72 12" fill="none" xmlns="http://www.w3.org/2000/svg">
              <g clip-path="url(#clip0_19_387)">
                <path d="M9.49492 11.6981C9.49492 7.87421 9.49492 4.08804 9.49492 0.30188C6.31734 0.30188 3.15236 0.30188 0 0.30188C0 4.1132 0 7.89936 0 11.6981C0.516988 11.6981 1.02137 11.6981 1.56357 11.6981C1.56357 8.35219 1.56357 5.03144 1.56357 1.69811C3.70718 1.69811 5.82557 1.69811 7.96918 1.69811C7.96918 5.04402 7.96918 8.36477 7.96918 11.6981C8.48617 11.6981 8.97793 11.6981 9.49492 11.6981Z" fill="#EE3124" />
                <path d="M49.0382 6.56604C49.0508 6.37736 49.0634 6.18868 49.0634 6C49.0634 5.69811 49.0382 5.4088 49.0004 5.1195C48.5716 2.22642 46.075 0 43.0487 0C39.7324 0 37.034 2.69182 37.034 6C37.034 9.30818 39.7324 12 43.0487 12C45.6715 12 47.8907 10.327 48.723 8H47.0837C46.3524 9.48428 44.814 10.4906 43.0487 10.4906C40.7538 10.4906 38.8498 8.77987 38.5723 6.55346L49.0382 6.56604ZM43.0487 1.50943C45.2301 1.50943 47.0585 3.06918 47.4746 5.13207H38.6354C39.0389 3.0566 40.8547 1.50943 43.0487 1.50943Z" fill="#EE3124" />
                <path d="M35.6469 10.2767L31.5362 1.06918C31.3345 0.603767 30.8679 0.30188 30.351 0.30188C29.834 0.30188 29.3674 0.603767 29.1657 1.06918L25.055 10.2767H23.6553V11.6981H37.0592V10.2767H35.6469ZM30.351 2.01257L33.9573 10.2767H26.7447L30.351 2.01257Z" fill="#EE3124" />
                <path d="M60.538 11.6981L56.049 1.08175C55.8473 0.61634 55.3933 0.314453 54.8763 0.314453C54.372 0.314453 53.9054 0.61634 53.7037 1.08175L49.2021 11.6981H50.8918L54.8637 2.10062L58.8483 11.6981H60.538Z" fill="#EE3124" />
                <path d="M70.4617 0.30188H70.4364L63.6273 9.53458V0.30188H62.089V11.6101V11.6981H63.6273L70.4617 2.44024V11.6981H72V0.364773V0.30188H70.4617Z" fill="#EE3124" />
                <path d="M17.5776 0C14.2487 0 11.5629 2.69182 11.5629 6C11.5629 9.30818 14.2613 12 17.5776 12C20.9065 12 23.5923 9.30818 23.5923 6C23.5923 2.69182 20.8939 0 17.5776 0ZM17.5776 10.5031C15.0935 10.5031 13.0634 8.49057 13.0634 6C13.0634 3.50943 15.0809 1.49686 17.5776 1.49686C20.0743 1.49686 22.0918 3.50943 22.0918 6C22.0918 8.49057 20.0616 10.5031 17.5776 10.5031Z" fill="#EE3124" />
                <path d="M17.5776 0.805092C17.5776 1.16987 17.8424 1.49692 18.2207 1.54723C20.2003 1.82396 21.7639 3.38371 22.0413 5.35855C22.0918 5.72333 22.4196 6.00006 22.7853 6.00006C23.2392 6.00006 23.5923 5.59755 23.5292 5.14472C23.151 2.51578 21.0578 0.427734 18.4224 0.062954C17.9685 6.08936e-05 17.5776 0.352262 17.5776 0.805092Z" fill="#263238" />
              </g>
              <defs>
                <clipPath id="clip0_19_387">
                  <rect width="72" height="12" fill="white" />
                </clipPath>
              </defs>
            </svg>
          </div>
        </div>
        <div class="modal-content">
          <p class="modal-content__title"> 25% сегодня, остальное – потом </p>
          <p class="modal-content__text"> Оплатите сегодня 25% стоимости покупки, а остальное — тремя платежами раз в две недели. </p>
          <div class="modal-content__info">
            <div class="modal-content__info-item">
              <span class="info-item-icon">
                <svg width="25" height="24" viewBox="0 0 25 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M21.2242 11.1112L21.2241 11.1112L21.2304 11.1207C21.4048 11.3822 21.5 11.6975 21.5 12.0221C21.5 12.3405 21.4063 12.6568 21.2315 12.9262L20.2871 14.3121L20.279 14.3241L20.271 14.3362C19.971 14.7905 19.771 15.2878 19.6643 15.7969L19.6627 15.8043L19.3136 17.5014L19.3136 17.5014L19.3119 17.5098C19.2477 17.8286 19.0926 18.1152 18.8702 18.3358C18.6306 18.5732 18.3389 18.728 18.0318 18.7898L18.0318 18.7898L18.0234 18.7915L16.3264 19.1407L16.3264 19.1406L16.319 19.1422C15.8099 19.2489 15.3125 19.4489 14.8583 19.7489L14.8581 19.749L13.4129 20.7036L13.4009 20.7116L13.3889 20.7197C13.1239 20.9003 12.816 20.9968 12.4939 21C12.1665 20.9988 11.8545 20.9035 11.596 20.7317L11.5941 20.7304L10.1758 19.779L10.1698 19.775L10.1638 19.771C9.7095 19.471 9.21213 19.271 8.7031 19.1643L8.69568 19.1628L6.99861 18.8136L6.99862 18.8136L6.9902 18.8119C6.6714 18.7478 6.38476 18.5927 6.16422 18.3702C5.92673 18.1306 5.77195 17.8389 5.71016 17.5319L5.71018 17.5319L5.70845 17.5235L5.36031 15.8313L5.35974 15.8285C5.24886 15.2846 5.02969 14.7708 4.72079 14.3173L3.77496 12.9073C3.59739 12.6372 3.50439 12.3249 3.50439 12C3.50439 11.6714 3.59938 11.3546 3.77172 11.0909L4.72096 9.67583L4.72499 9.66983L4.72897 9.66379C5.02896 9.20953 5.22894 8.71215 5.33567 8.20312L5.33569 8.20312L5.33721 8.19571L5.68635 6.49864L5.68867 6.48736L5.69086 6.47605C5.75221 6.1594 5.90628 5.8701 6.13155 5.64483C6.36492 5.41146 6.65979 5.2524 6.97851 5.18596L6.97993 5.18567L8.66869 4.83824L8.67146 4.83767C9.17928 4.73419 9.68526 4.53922 10.1494 4.23837L10.1567 4.23365L10.164 4.22886L11.6091 3.27426L11.6105 3.27333C11.8816 3.09396 12.1955 3 12.5221 3C12.8486 3 13.1636 3.09383 13.4263 3.26416L14.8121 4.20843L14.8241 4.21659L14.8362 4.22457C15.2904 4.52456 15.7878 4.72455 16.2969 4.83128L16.3004 4.83202L18.0019 5.18557L18.0019 5.18561L18.0142 5.18809C18.333 5.25225 18.6196 5.40733 18.8401 5.6298C19.0776 5.86937 19.2324 6.16111 19.2942 6.46813L19.2942 6.46813L19.2959 6.47654L19.6441 8.16871L19.6446 8.17137C19.7548 8.7121 19.972 9.22299 20.278 9.67451L21.2242 11.1112ZM21.9399 15.4383L22.8945 14.0374C23.2878 13.4407 23.5 12.738 23.5 12.0221C23.5 11.3106 23.2923 10.6079 22.8945 10.0112L21.9399 8.56167C21.7764 8.32302 21.6615 8.05344 21.604 7.77059L21.2549 6.07352C21.1135 5.37083 20.7643 4.73001 20.2605 4.22178C19.7567 3.71354 19.1115 3.36882 18.4088 3.2274L16.7073 2.87385C16.4333 2.81639 16.1725 2.71033 15.9383 2.55564L14.5373 1.60104C13.9407 1.20771 13.238 1 12.5221 1C11.8061 1 11.1079 1.20771 10.5068 1.60546L9.06165 2.56006C8.823 2.71474 8.55341 2.82081 8.27057 2.87826L6.5735 3.2274C5.87081 3.37324 5.22557 3.72238 4.71733 4.23061C4.2091 4.73885 3.86438 5.38851 3.72738 6.09562L3.37824 7.79269C3.32079 8.06669 3.21472 8.32744 3.06004 8.56167L2.10544 9.98473C1.71211 10.5814 1.50439 11.284 1.50439 12C1.50439 12.716 1.71211 13.4142 2.10986 14.0153L3.06446 15.4383C3.22798 15.677 3.34288 15.9466 3.40034 16.2294L3.74947 17.9265C3.8909 18.6292 4.24003 19.27 4.74385 19.7782C5.24767 20.2865 5.8929 20.6312 6.5956 20.7726L8.29266 21.1217C8.56667 21.1792 8.82742 21.2853 9.06165 21.4399L10.4847 22.3945C11.0813 22.7923 11.784 23 12.5 23C13.2203 22.9956 13.9186 22.779 14.5152 22.3724L15.9604 21.4178C16.1946 21.2632 16.4554 21.1571 16.7294 21.0996L18.4265 20.7505C19.1291 20.6091 19.77 20.2599 20.2782 19.7561C20.7864 19.2523 21.1312 18.6071 21.2726 17.9044L21.6217 16.2073C21.6792 15.9333 21.7852 15.6726 21.9399 15.4383ZM10.3023 9.79787C10.9097 9.79787 11.4021 9.30548 11.4021 8.69809C11.4021 8.0907 10.9097 7.59831 10.3023 7.59831C9.69489 7.59831 9.2025 8.0907 9.2025 8.69809C9.2025 9.30548 9.69489 9.79787 10.3023 9.79787ZM15.8012 15.2968C16.4086 15.2968 16.901 14.8044 16.901 14.197C16.901 13.5896 16.4086 13.0972 15.8012 13.0972C15.1938 13.0972 14.7014 13.5896 14.7014 14.197C14.7014 14.8044 15.1938 15.2968 15.8012 15.2968ZM14.0577 8.88689C14.4872 8.4574 15.1836 8.4574 15.6131 8.88689C16.0425 9.31638 16.0425 10.0127 15.6131 10.4422L10.9471 15.1082C10.5176 15.5377 9.82125 15.5377 9.39176 15.1082C8.96227 14.6787 8.96227 13.9823 9.39176 13.5529L14.0577 8.88689Z" fill="#455A64" />
                </svg>
              </span>
              <span class="info-item-text"> Без процентов и комиссий </span>
            </div>
            <div class="modal-content__info-item">
              <span class="info-item-icon">
                <svg width="25" height="24" viewBox="0 0 25 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M2.5 7C2.5 5.34315 3.84315 4 5.5 4H19.5C21.1569 4 22.5 5.34315 22.5 7V17C22.5 18.6569 21.1569 20 19.5 20H5.5C3.84315 20 2.5 18.6569 2.5 17V7ZM5.5 6C4.94772 6 4.5 6.44772 4.5 7V9H17.5C18.0523 9 18.5 9.44772 18.5 10C18.5 10.5523 18.0523 11 17.5 11H4.5V17C4.5 17.5523 4.94772 18 5.5 18H19.5C20.0523 18 20.5 17.5523 20.5 17V7C20.5 6.44772 20.0523 6 19.5 6H5.5ZM15.5 14C14.9477 14 14.5 14.4477 14.5 15C14.5 15.5523 14.9477 16 15.5 16H17.5C18.0523 16 18.5 15.5523 18.5 15C18.5 14.4477 18.0523 14 17.5 14H15.5Z" fill="#455A64" />
                </svg>
              </span>
              <span class="info-item-text"> Быстро и удобно как обычная оплата картой </span>
            </div>
          </div>
          <div class="wiget-block-split">
            <div class="wiget-block-split__item">
              <p class="split-item-day">Сегодня</p>
              <p class="split-item-payment">' . $price / 4 . ' ₽</p>
            </div>
            <div class="wiget-block-split__item wiget-block-split__item_disable">
              <p class="split-item-day">' . $podeli_2w_date . '</p>
              <p class="split-item-payment">' . $price / 4 . ' ₽</p>
            </div>
            <div class="wiget-block-split__item wiget-block-split__item_disable">
              <p class="split-item-day">' . $podeli_4w_date . '</p>
              <p class="split-item-payment">' . $price / 4 . ' ₽</p>
            </div>
            <div class="wiget-block-split__item wiget-block-split__item_disable">
              <p class="split-item-day">' . $podeli_6w_date . '</p>
              <p class="split-item-payment">' . $price / 4 . ' ₽</p>
            </div>
          </div>
        </div>
        <a href="/checkout/?add-to-cart=' . $product_id . '&source=podeli_widget" class="modal-link"> оформить покупку в 4 платежа </a>
      </div>
    </div>
  </div>
 </div>
';
        }
    }
}

function podeli_cart_widget()
{
    global $woocommerce;

    $cart = $woocommerce->cart;

    $price = $cart->get_total();
    $price = preg_replace('/[^\d.,]/', '', $price);
    $price = number_format(floatval(str_replace(',', '.', $price)), 2, '.', '');

    setlocale(LC_TIME, 'ru_RU.UTF-8');
    $podeli_2w_date = strftime('%d %b', strtotime('+2 weeks'));
    $podeli_4w_date = strftime('%d %b', strtotime('+4 weeks'));
    $podeli_6w_date = strftime('%d %b', strtotime('+6 weeks'));

    if (get_option('robokassa_payment_podeli_widget_onoff') === 'true' && $price > 300 && $price < 30000) {
        echo '
<div class="wiget-block-wrapper">
  <div class="wiget-block">
    <div class="wiget-block__content">
      <div class="wiget-block__logotypes">
        <div class="wiget-block__logotype">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="enable-background:new 0 0 24 24;" xml:space="preserve">
            <style type="text/css">
              .st0 {
                fill: #023D5E;
              }

              .st1 {
                fill: #FF5722;
              }

              .st2 {
                fill: #FFFFFF;
              }

              .st3 {
                opacity: 0.7;
                fill: #FFFFFF;
              }

              .st4 {
                fill: #FFE3BF;
              }

              .st5 {
                fill: #FF0001;
                stroke: #FF0001;
                stroke-width: 0.2;
              }

              .st6 {
                stroke: #000000;
                stroke-width: 0.2;
              }
            </style>
            <circle class="st2" cx="12" cy="12" r="12" />
            <g>
              <path class="st1" d="M6,6v12h12L6,6z" />
              <path class="st0" d="M18,6H6v12L18,6z" />
            </g>
          </svg>
        </div>
        <div class="wiget-block__logotype">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="24" height="24" rx="12" fill="#EE3124" />
            <path d="M12 4C7.57233 4 4 7.5891 4 12C4 16.4109 7.5891 20 12 20C16.4277 20 20 16.4109 20 12C20 7.5891 16.4109 4 12 4ZM12 18.0042C8.69602 18.0042 5.99581 15.3208 5.99581 12C5.99581 8.67925 8.67924 5.99581 12 5.99581C15.3208 5.99581 18.0042 8.67925 18.0042 12C18.0042 15.3208 15.304 18.0042 12 18.0042Z" fill="white" />
            <path d="M11.9414 4.99924C11.9414 5.48561 12.2936 5.92167 12.7968 5.98876C15.4299 6.35773 17.5095 8.43739 17.8785 11.0705C17.9456 11.5569 18.3817 11.9259 18.868 11.9259C19.4718 11.9259 19.9414 11.3892 19.8575 10.7854C19.3544 7.28016 16.5703 4.49609 13.0651 4.00972C12.4613 3.92586 11.9414 4.39546 11.9414 4.99924Z" fill="#1D2939" />
          </svg>
        </div>
      </div>
      <div class="wiget-block__title-subtitle">
        <h5 class="wiget-block__title">
          <span class="wiget-block__months">4</span> платежа по <span class="wiget-block__payment">' . $price / 4 . '</span> ₽
        </h5>
        <p class="wiget-block__subtitle"> Без комиссий и переплат </p>
      </div>
    </div>
   
  </div>
</div>
    ';
    }
}

function select_payment_method($available_gateways)
{
    // Проверяем, является ли текущая страница страницей оформления покупки
    if (is_checkout()) {
        if (isset($_GET['source']) && $_GET['source'] === 'podeli_widget') {
            // Устанавливаем метод оплаты "wc_payment_method payment_method_Podeli" как выбранный
            WC()->session->set('chosen_payment_method', 'Podeli');
        }
    }

    return $available_gateways;
}

function podeli_checkout_widget()
{
    global $woocommerce;

    $cart = $woocommerce->cart;

    $price = $cart->get_total();
    $price = preg_replace('/[^\d.,]/', '', $price);
    $price = number_format(floatval(str_replace(',', '.', $price)), 2, '.', '');

    setlocale(LC_TIME, 'ru_RU.UTF-8');
    $podeli_2w_date = strftime('%d %b', strtotime('+2 weeks'));
    $podeli_4w_date = strftime('%d %b', strtotime('+4 weeks'));
    $podeli_6w_date = strftime('%d %b', strtotime('+6 weeks'));

    if (get_option('robokassa_payment_podeli_widget_onoff') === 'true' && $price > 300 && $price < 30000) {
        echo '
<div class="wiget-block__prompt">
  <div class="wiget-block__content">
    <div class="wiget-block__title-checkout_subtitle">
      <h5 class="wiget-block__title">
        <span class="wiget-block__months">4</span> платежа по <span class="wiget-block__payment">' . $price / 4 . '</span> ₽
      </h5>
      <p class="wiget-block__subtitle"> Без комиссий и переплат </p>
    </div>
    <div class="wiget-block__checkout_logotypes">
      <div class="wiget-block__logotype">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="24" height="24" rx="12" fill="FFFFFF"/>
                            <path d="M6,6v12h12L6,6z" fill="#FF5722"/>
                            <path d="M18 6H6V18L18 6Z" fill="#023D5E"/>
                        </svg>
      </div>
      <div class="wiget-block__logotype">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="24" height="24" rx="12" fill="#EE3124" />
          <path d="M12 4C7.57233 4 4 7.5891 4 12C4 16.4109 7.5891 20 12 20C16.4277 20 20 16.4109 20 12C20 7.5891 16.4109 4 12 4ZM12 18.0042C8.69602 18.0042 5.99581 15.3208 5.99581 12C5.99581 8.67925 8.67924 5.99581 12 5.99581C15.3208 5.99581 18.0042 8.67925 18.0042 12C18.0042 15.3208 15.304 18.0042 12 18.0042Z" fill="white" />
          <path d="M11.9414 4.99924C11.9414 5.48561 12.2936 5.92167 12.7968 5.98876C15.4299 6.35773 17.5095 8.43739 17.8785 11.0705C17.9456 11.5569 18.3817 11.9259 18.868 11.9259C19.4718 11.9259 19.9414 11.3892 19.8575 10.7854C19.3544 7.28016 16.5703 4.49609 13.0651 4.00972C12.4613 3.92586 11.9414 4.39546 11.9414 4.99924Z" fill="#1D2939" />
        </svg>
      </div>
    </div>
  </div>
  <div class="wiget-block-split">
    <div class="wiget-block-split__item">
      <p class="split-item-day">Сегодня</p>
      <p class="split-item-payment">' . $price / 4 . ' ₽</p>
    </div>
    <div class="wiget-block-split__item wiget-block-split__item_disable">
      <p class="split-item-day">' . $podeli_2w_date . '</p>
      <p class="split-item-payment">' . $price / 4 . ' ₽</p>
    </div>
    <div class="wiget-block-split__item wiget-block-split__item_disable">
      <p class="split-item-day">' . $podeli_4w_date . '</p>
      <p class="split-item-payment">' . $price / 4 . ' ₽</p>
    </div>
    <div class="wiget-block-split__item wiget-block-split__item_disable">
      <p class="split-item-day">' . $podeli_6w_date . '</p>
      <p class="split-item-payment">' . $price / 4 . ' ₽</p>
    </div>
  </div>
</div>
                ';
    }
}

add_filter('woocommerce_available_payment_gateways', 'select_payment_method');

add_action('woocommerce_single_product_summary', 'podeli_product_widget', 25);
add_action('woocommerce_proceed_to_checkout', 'podeli_cart_widget');