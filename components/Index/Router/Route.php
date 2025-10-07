            <?php
            $decryptedPage = 'admin_dashboard'; // default page
            if (isset($_GET['page'])) {
                $decrypted = decrypt($_GET['page']);
                if ($decrypted !== false) {
                    $decryptedPage = $decrypted;
                }
            }

            switch ($decryptedPage) {
                case 'admin_dashboard':
                    include 'api/admin_dashboard.php';
                    break;
                case 'schedule_appointment':
                    include 'api/schedule_appointment.php';
                    break;
                case 'resident_appointment':
                    include 'api/resident_appointment.php';
                    break;
                case 'resident_profile':
                    include 'api/resident_profile.php';
                    break;
                case 'event_calendar':
                    include 'api/event_calendar.php';
                    break;
                case 'settings_section':
                    include 'auth/settings/settings.php';
                    break;
                case 'homepage':
                    include 'api/homepage.php';
                    break;
                case 'upload_profile':
                    include 'class/upload_profile.php';
                    break;
                case 'audit':
                    include 'api/audit_logs.php';
                    break;                    
                case 'cp_2fa':
                    include 'auth/cp_2fa.php';
                    break;
                default:
                    echo "<div class='alert alert-danger'>Invalid or missing page.</div>";
            }
            ?>