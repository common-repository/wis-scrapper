jQuery(document).ready(function($){
	/** Multi Step Slider **/
	var current_fs, next_fs, previous_fs;
	var left, opacity, scale;
	var animating;
	var package_created = false, package_url = "";
	$(window).load(function () {
		$("#middle-div-two .p-second,#popup-copy-clipboard").click(function(){
			if( $('.hover_bkgr_fricc').css("display") == "none" ){
				$('.hover_bkgr_fricc').show();
			}
			else{
				$(".popupCloseButton").click();
				setTimeout(function(){$("#popup-copy-clipboard").click()},1000);
			}
			if( package_created ){
				copyToClipBoard();
			}
		});
		$("#popup-copy-clipboard").on("click",function(){
			event.stopPropagation();
		});
		$('.hover_bkgr_fricc,.popupCloseButton').click(function(){
			$(".trigger").removeClass("drawn");
			$('.hover_bkgr_fricc').hide();
			$("#popup-message").text("Backup Downloaded successfully");
			$("#popup-copy-clipboard").hide();
			$("#popup-download-backup").show();
		});
	});
	function copyToClipBoard(){
		$("input[name=zip_url]").select();
		var test = document.execCommand("copy");
		$(".trigger").addClass("drawn");
	}
	$(".next").click(function(){
		$('html, body').scrollTop( 0 );
		if(animating) return false;
		animating = true;
		current_fs = $(this).closest("fieldset.single-block");
		next_fs = current_fs.next();
		if( current_fs.find("form").length ){
			current_fs.find("form").ajaxSubmit({
				success:function( response ){
					var form_type = current_fs.find("form").data("form-type");
					if( form_type == "System Scanning" ){
						$("#second-step-form").ajaxSubmit({
							success:function( response ){
								if( response['Status'] == "1" ){
									package_created = true;
									package_url = response['CompleteZipURL'];
									$("#backup-complete").show();
									$("#creating-backup").hide();
									$("input[name=zip_url]").val( package_url );
									$("#popup-download-backup").attr("href", package_url);
									if( $(".hover_bkgr_fricc").css("display") == "block"){
										copyToClipBoard();
										$("#popup-message").text("Backup Created Successfully");
										$("#popup-copy-clipboard").show();
									}
								}
							},
							error:function(){
								alert("Error while creating Package!!");
							}
						});
					}
				},
				error:function(){
					alert("Error while making Scanning!!");
				}
			});
		}else{}
	});
	$(".previous").click(function(){
		$('html, body').scrollTop( 0 );
		if(animating) return false;
		animating = true;
		current_fs = $(this).closest("fieldset.single-block");
		previous_fs = current_fs.prev();
		$("#progressbar li").eq($("fieldset").index(current_fs)).removeClass("active");
		previous_fs.show(); 
		current_fs.animate({opacity: 0}, {
			step: function(now, mx) {
				scale = 0.8 + (1 - now) * 0.2;
				left = ((1-now) * 50)+"%";
				opacity = 1 - now;
				current_fs.css({'left': left});
				previous_fs.css({'transform': 'scale('+scale+')', 'opacity': opacity});
			}, 
			duration: 800, 
			complete: function(){
				current_fs.hide();
				animating = false;
			}, 
			easing: 'easeInOutBack'
		});
	});
	$(".submit").click(function(){
		return false;
	});
	/* END Multi Step Slider */
	/* First Step JS */
	$(".exclude-filter a").on("click",function(){
		if( $(this).data("path") ){
			var text_area = $(this).parents(".exclude-filter").find("textarea");
			text_area.val( text_area.val() + $(this).data("path")+";" );
		}
	});
	$("input[name='export-onlydb']").on("change",function(){
		if( $(this).is(":checked") ) {
			$("#only-database-notice").show();
			$("#file-settings").hide();
			$(" input[name='filter-on'").parent("label").hide();
		}else{
			$("#only-database-notice").hide();
			$("#file-settings").show();
			$(" input[name='filter-on'").parent("label").show();
		}
	});
	$("input[name='filter-on']").on("change",function(){
		if( $(this).is(":checked") ) {
			$("#file-settings").css("color","rgb(0, 0, 0)");
			$("#file-settings textarea").removeAttr('readonly').css("color","#000");
		}else{
			$("#file-settings").css("color","rgb(153, 153, 153)");
			$("#file-settings textarea").attr('readonly', 'readonly').css("color","#999");
		}
	});
	$("input[name='dbfilter-on']").on("change",function(){
		if( $(this).is(":checked") ) {
			$('#wis-table-names input').removeAttr('readonly');
		}else{
			$('#wis-table-names input').attr('readonly', 'readonly');
		}
	});
	$("#wis-filter-tables #include-all").on("click",function(){
		event.preventDefault();
		$('#wis-table-names input').prop("checked",false);
	});
	$("#wis-filter-tables #exclude-all").on("click",function(){
		event.preventDefault();
		$('#wis-table-names input').prop("checked",true);
	});
	$("input[name='secure-on']").on("change",function(){
		if( $(this).is(":checked") ) {
			$("#wis-enter-password").show();
		}else{
			$("#wis-enter-password").hide();
		}
	});
	$("#wis-enter-password button").on("click",function(){
		event.preventDefault();
		var $input  = $('#wis-enter-password input');
		var $button =  $(this);
		if (($input).attr('type') == 'text') {
			$input.attr('type', 'password');
			$button.html('<i class="fa fa-eye"></i>');
		} else {
			$input.attr('type', 'text');
			$button.html('<i class="fa fa-eye-slash"></i>');
		}
	});
	/** End First Step JS **/
	$("#first-step-submit").click();
});
function openTab(evt, cityName) {
	evt.preventDefault();
	var i, x, tablinks;
	x = document.getElementsByClassName("wis-tabs");
	for (i = 0; i < x.length; i++) {
		x[i].style.display = "none";
	}
	tablinks = document.getElementsByClassName("tablink");
	for (i = 0; i < x.length; i++) {
		tablinks[i].className = tablinks[i].className.replace(" w3-dark-grey", "");
	}
	document.getElementById(cityName).style.display = "block";
	evt.currentTarget.className += " w3-dark-grey";
}