jQuery(document).ready(function($) {
	var data;
	$('form.cr-ip-form').submit(function(e){
	e.preventDefault();
	var respons_ip = $(this).children('.response-ip');
		data = {
			action: 'cr_ip_action',
			security : MyAjax.security,
			ip: $(this).children('.cr-ip-input').val()
		};
		
		$.post(MyAjax.ajaxurl, data, function(response) {
			respons_ip.html(response);
		});
		
	});
});