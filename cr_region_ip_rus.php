<?php
	/*
		Plugin Name: Cr Region IP RUS
		Plugin URI: https://github.com/WP-Panda/cr_regions_ip
		Description: Плагин для определения Города и региона посетителя по IP (Версия Российская федерация), В данной версии отсутствуют Крым и Калининградская область.)
		Version: 0.1.1
		Author: Максим (WP_panda) Попов
		Author Email: yoowordpress@yandex.ru
		License: GPL v3
		
		Copyright 2014 Максим (WP_panda) Попов (yoowordpress@yandex.ru)
		
		This program is free software: you can redistribute it and/or modify
		it under the terms of the GNU General Public License as published by
		the Free Software Foundation, either version 3 of the License, or
		(at your option) any later version.
		
		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.
		
		You should have received a copy of the GNU General Public License
		along with this program.  If not, see <http://www.gnu.org/licenses/>.
	*/
	
	register_activation_hook( __FILE__, 'myplugin_activate' );
	register_deactivation_hook( __FILE__, 'myplugin_deactivate' );
	
	// действия при активации
	function myplugin_activate() {
		global $wpdb;
		$table_name = $wpdb->prefix . "cr_regions_citys";
		
		$sqlfile = plugin_dir_path(__FILE__) . '/cr_region_ip_base.sql';
		if (!file_exists($sqlfile));
		$open_file = fopen ($sqlfile, "r");
		$data = fread($open_file, filesize($sqlfile));
		fclose ($open_file);
		
		$data  = str_replace( 'wp_cr', $wpdb->prefix . 'cr', $data );
		
		$a = 0;
		while ( $b = strpos( $data,";",$a+1 ) ) {
			$a = substr( $data,$a+1,$b-$a );
			$wpdb->query( $a );
			$a = $b;
		}
	}
	
	//действия при деактивации
	function myplugin_deactivate(){
		global $wpdb;	
		$wpdb->query("DROP TABLE `" . $wpdb->prefix . "cr_regions_citys`, `" . $wpdb->prefix . "cr_regions_ip_pulls`, `" . $wpdb->prefix . "cr_regions_regions_name`");
	}		
	
	//определение ip
	function get_conwert_ip_too_region( $ip=null ) {
		global $wpdb;
		$output = array();
		if( empty ( $ip ) ) {
			//получаем реальный ip
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$ip=$_SERVER['HTTP_CLIENT_IP'];
				} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
				} else {
				$ip=$_SERVER['REMOTE_ADDR'];
			}
			
			$output['ip'] = $ip;
		}
		
		
		$int = sprintf( "%u", ip2long( $ip ) ); //форматируем ip
		
		$region_name = "";
		$region_id = 0;
		$city_name = "";
		$city_id = 0;
		
		$search_ip = $wpdb->get_results( "SELECT * FROM " . $wpdb->prefix  . "cr_regions_ip_pulls WHERE begin_ip<=" . $int . " AND end_ip>=" . $int . ""); // ищщем ip в списке
		
		if ( $search_ip ) {
			$city_id = $search_ip[0]->city_id; // получаем id города
			$city_names = $wpdb->get_results( "SELECT * FROM  " . $wpdb->prefix  . "cr_regions_citys WHERE id='" . $city_id."'"); // получаем город 
			if ($city_names) {
				$city_name = $city_names[0] -> name_ru; // название города
				$region_id = $city_names[0] -> region; // id региона
				$region =  $wpdb->get_results( "SELECT * FROM  " . $wpdb->prefix  . "cr_regions_regions_name WHERE id='" . $region_id . "'"); // получаем region
				$region_name = $region[0]  -> reg_names;
				} else {
				$city_id = 0;
			}
		}
		
		if ($city_id == 0) {
			$output['city'] = __('Город не определен, или находится не на территории Российской Федерации','wp_panda');
			$output['city_id'] = ''; 
		} else {
			$output['city'] = $city_name;
			$output['city_id'] = $city_id; 
		}
		
		if ($region_id == 0) {
			$output['region'] = __('Регион не определен, или находится не на территории Российской Федерации','wp_panda');
			$output['region_id'] = '';
		} else {
			$output['region'] = $region_name;
			$output['region_id'] = $region_id;
		}	
		
		return $output;
		
	}
	
	// подключение js
	if( !is_admin() ) {
		function cr_ip_scripts() {
			wp_enqueue_script( 'cr-ip-frontend', plugin_dir_url(__FILE__) . '/assets/js/cr_ip_frontend.js', array('jquery'), '1.0.0', true );
			wp_localize_script( 'cr-ip-frontend', 'MyAjax', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'security' => wp_create_nonce( 'cr-ip-special-string' )
			));
		}
	
		add_action( 'wp_enqueue_scripts', 'cr_ip_scripts' );
	}
	
	// ajax для формы
	function cr_ip_action_callback() {
		check_ajax_referer( 'cr-ip-special-string', 'security' );
		
		$ip = mysql_real_escape_string(htmlspecialchars(strip_tags(trim( $_POST['ip'] ) ) ) );	
		if (filter_var($ip, FILTER_VALIDATE_IP) || empty($ip) ) {
			$output = get_conwert_ip_too_region($ip);
			if ( empty($ip) ) echo 'Ваш IP - ' . $output['ip'] . '<br>';
			echo '<span>' . $output['region'] . '</span><br>';
			echo '<span>' . $output['city'] . '</span>';
			echo  '<br>' .  $output['region_id'] . '<br>' . $output['city_id'];
		} else {
			_e('введите корректный ip','wp_panda');
		}
		
		die();
	}
	add_action( 'wp_ajax_cr_ip_action', 'cr_ip_action_callback' );
	
	//форма
	
	function cr_ip_region_form() { 
	$out= "<form class='cr-ip-form'>";
		$out .= "<div class='response-ip'></div>";
		$out .= "<input class='cr-ip-input' type='text' name='cr-ip-input' value=''>";
		$out .= "<input type='submit' class='cr-ip-button' value='Определить'>";
	$out .= "</form>";
	echo $out;
	}

	//шорткод для вывода
	add_shortcode( 'cr_ip_form' , 'cr_ip_region_form');