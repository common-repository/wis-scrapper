<style>
#wpcontent{	margin-left:140px;}
#wpfooter{display:none;}
#wpbody-content{padding-bottom:0;}
#wis-scrapperadd-form #msform fieldset:last-of-type{display:block;}
#wis-scrapperadd-form #msform fieldset{display:none;}
#progressbar{display:none;}
</style>
<div id="wis-scrapperadd-form">
	<div id="msform">
		<!-- progressbar -->
		<ul id="progressbar">
			<li class="active">Backup Setup</li>
			<li <?php echo empty($_GET['backup'])?'':'class="active"' ?>>System Scan</li>
			<li <?php echo empty($_GET['backup'])?'':'class="active"' ?>>Build</li>
		</ul>
		<fieldset class="single-block">
			<form action="<?php echo admin_url( 'admin-ajax.php' ); ?>" id="step-1" method="post" data-form-type="System Scanning">
				<input type="hidden" name="action" value="backup_setup">
				<input type="text" name="package-name" value="Wis_Scrapper_Backup" required placeholder="Name" />
				<div style="text-align:center">
					<input type="button" name="next" class="next action-button" id="first-step-submit" value="Next" />
				</div>
			</form>
		</fieldset>
		<fieldset class="single-block step-2">
			<div style="text-align:center">
				<form action="<?php echo admin_url( 'admin-ajax.php' ); ?>" data-form-type="Building Package" id="second-step-form" method="post">
					<input type="hidden" name="action" value="package_build" />
				</form>
			</div>
		</fieldset>
		<fieldset class="single-block step-3">
			<h2 class="fs-title">Welcome to easy Wordpress scrapper</h2>
			<h3 id="second-title">Let's get your site to your new hosting</h3>
			<div id="step-3-ajax-response">
				<?php include("multi-step/wis-step-3.php"); ?>
			</div>
		</fieldset>
	</div>
</div>