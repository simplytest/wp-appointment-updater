<?php use SimplyTest\AppointmentUpdater\admin\Appointment_Updater_Admin;
use SimplyTest\AppointmentUpdater\includes\DEBUG;

?>
<div class="wrap" style="display:flex; justify-content: center;">
    <form method="post" action="options.php">
        <?php
         // This prints out all hidden setting fields
         settings_fields( Appointment_Updater_Admin::OPTION_GROUP);
         do_settings_sections( Appointment_Updater_Admin::MENU_SLUG );
         submit_button();
         ?>
    </form>
</div>
