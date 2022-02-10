<?php
$config_defaults = array(
    'is_logged_in' => false,
);
$config = get_option('adbirt_publisher_config', $config_defaults);

$config['categories'] = $this->get_categories();
$config['campaigns'] = $config['campaigns'] ?? array();

$alertMessages = array();

if (isset($_POST['logout']) && $_POST['logout'] == 'true') {
    update_option('adbirt_publisher_config', $config_defaults);
    $config = $config_defaults;

    array_push($alertMessages, array(
        'message' => 'You\'ve logged out successfully.',
        'severity' => 'success'
    ));
}

if ($config['is_logged_in'] === false) {

    if (isset($_POST['email'])) {
        if (isset($_POST['password'])) {
            $email = $_POST['email'];
            $password = $_POST['password'];

            $remote_response = wp_remote_post('https://adbirt.com/login', array(
                'timeout'     => 4000,
                'redirection' => 15,
                'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
                'blocking' => true,
                'body' => array(
                    'email' => $email,
                    'password' => $password,
                    'is_remote_request' => 'true'
                )
            ));

            $body = json_decode($remote_response['body'], true, 512 * 2, JSON_THROW_ON_ERROR);

            function loginError(string $errorMessage, string $severity, &$config, &$alertMessages)
            {
                $config['is_logged_in'] = false;
                $config['user'] = null;

                array_push($alertMessages, array(
                    'message' => $errorMessage,
                    'severity' => $severity
                ));
            };

            if (intval($body['status']) == 300) {
                loginError($body['message'], 'info', $config, $alertMessages);
            } elseif (intval($body['status']) == 400) {
                loginError($body['message'], 'danger', $config, $alertMessages);
            } elseif (intval($body['status']) == 500) {
                loginError('An error occurred on the server. Please try again later.', 'danger', $config, $alertMessages);
            } elseif (intval($body['status']) == 200) {
                $user = $config['user'] = $body['payload'];

                // user properties >>
                // active: 1
                // address: "NO 4, Jesus Avenue Canaan Estate, Along Lekki garden phase 1"
                // birthday: "2017-07-05"
                // city: "Lagos"
                // country: "Nigeria"
                // created_at: "2019-09-12 07:13:49"
                // email: "adbirtofficial@gmail.com"
                // id: 49
                // login: "email"
                // name: "Adbirt"
                // phone: null
                // updated_at: "2022-01-07 08:47:10"

                if (intval($user['active']) == 1) {
                    $config['is_logged_in'] = true;
                    $config['user'] = $body['payload'];

                    $role = ''; // 'admin', 'advertiser', 'publisher'

                    switch (intval($body['role_id'])) {
                        case 1:
                            $user['role'] = 'admin';
                            break;

                        case 2:
                            $user['role'] = 'advertiser';
                            break;

                        case 3:
                            $user['role'] = 'publisher';
                            break;
                    }

                    if ($user['role'] != 'publisher') {
                        loginError('Only Publisher accounts are allowed! Visit https://adbirt.com/register to sign up as a publisher', 'danger', $config, $alertMessages);
                    }

                    $config['campaigns'] = $body['campaigns'];

                    $user['propic'] = $body['propic'];
                    $config['user'] = $user;
                    // done
                } else {
                    loginError('Your account has been deactivated, contact info@adbirt.com to rectify this.', 'danger', $config, $alertMessages);
                }
            } else {
                loginError('Something went wrong. Please try again later.', 'danger', $config, $alertMessages);
            }
        } else {
            array_push($alertMessages, array(
                'message' => 'Please enter your password.',
                'severity' => 'danger'
            ));
        }
    }
    // else skip
}

// 
// apply state to UI and render it
// 

ob_start();
?>
<!-- begin wrap -->
<div class="wrap">
    <!-- begin style section -->
    <link rel="stylesheet" href="<?php echo trailingslashit(plugin_dir_url(__FILE__)) . 'assets/css/dashboard-styles.css'; ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:regular,bold,italic,thin,light,bolditalic,black,medium&amp;lang=en">
    <link rel="stylesheet" href="https://adbirt.com/public/dist/css/style.min.css">
    <link rel="stylesheet" href="https://adbirt.com/public/assets-revamp/bootstrap/css/bootstrap.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous" />
    <!-- end style section -->

    <?php
    if ($config['is_logged_in'] === true) {
    ?>
        <div class="container-sm">

            <br />

            <div class="row">
                <div class="col-12 col-md-4">
                    <img src="<?php echo $config['user']['propic'] ?? 'https://adbirt.com/public/assets-revamp/img/avatar.png'; ?>" alt="<?php echo $user['name']; ?>" title="<?php echo $user['name']; ?>" class="w-100 img-circle bg-warning border border-warning" />
                </div>

                <div class="col-12 col-md-8 d-flex flex-column align-items-start justify-content-center">
                    <ul class="card w-100">
                        <li>
                            <strong>Username:</strong> <?php echo $config['user']['name']; ?>
                        </li>
                        <li>
                            <strong>Email:</strong> <?php echo $config['user']['email']; ?>
                        </li>
                        <li>
                            <strong>Role:</strong> <?php echo $config['user']['role']; ?>
                        </li>
                        <li>
                            <form method="post">
                                <input type="hidden" name="logout" value="true">
                                <button type="submit" class="btn btn-danger">Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>

            <br />

            <!-- start copy -->
            <section class="content">
                <div class="container-fluid yield-content">

                    <!-- Begin yield content -->
                    <!-- Content -->
                    <div class="layout-content" data-scrollable="" id="mainDiv">
                        <div class="w-100">

                            <div class="viewtable">
                                <h3 class="active">My Running Ads</h3>
                                <p>Copy the shortcode for any campaign and place it where you want the ad to show</p>
                                <div class="card w-100 mw-100">
                                    <div class="table-responsive">
                                        <table id="datatable-example" class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Category</th>
                                                    <th>Type</th>
                                                    <th>Price</th>
                                                    <th>Short code</th>
                                                    <!-- <th>Action</th> -->
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                foreach ($config['campaigns'] as $campaignIndex => $campaign) {
                                                ?>
                                                    <tr class="data-11">
                                                        <td class="campaigns_name"><?php echo ucwords($campaign['campaign']['campaign_name']); ?></td>
                                                        <td class="campaigns_name"><?php echo $this->getCategoryNameFromId($config['categories'], $campaign['campaign']['campaign_category']); ?></td>
                                                        <td class="campaigns_name"><?php echo ucwords($campaign['campaign']['campaign_type']); ?></td>
                                                        <td class="campaigns_name"><?php echo $campaign['campaign']['campaign_cost_per_action']; ?></td>
                                                        <td>
                                                            <div class="row input-group mb-3 w-75">
                                                                <input type="text" value='<a class="ubm-banner" data-id="<?php echo base64_encode($campaign["advert_code"]); ?>"></a>' class="form-control" id="source-code-<?php echo $campaignIndex; ?>" readonly="">
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text copy-btn btn btn-info" title="Copy to clipboard" data-clipboard-target="#source-code-<?php echo $campaignIndex; ?>" data-clipboard-action="copy">
                                                                        <i class="fa fa-copy"></i>
                                                                        <!-- &nbsp;
                                                                                Copy -->
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <!-- <td>
                                                                    <a href="https://adbirt.com/campaigns/view-my-campaign/MTE=" class="btn btn-info">
                                                                        <i class="fa fa-eye"></i>
                                                                    </a>
                                                                </td> -->
                                                    </tr>
                                                <?php
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div><!-- /.box-body -->
                            </div><!-- /.box -->
                        </div>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </section>
            <!-- end copy -->

        </div>
    <?php
    } elseif ($config['is_logged_in'] === false) {
    ?>
        <!-- begin login section -->
        <div class="login">
            <div class="row w-100">
                <div class="col-sm-8 col-sm-push-1 col-md-4 col-md-push-4 col-lg-4 col-lg-push-4">

                    <div class="row justify-content-center">
                        <div class="center m-a-2">
                            <div class="icon-block img-circle">
                                <a target="_blank" href="https://adbirt.com"><img src="http://adbirt.com/public/assets-revamp/img/favicon.png" /></a>
                            </div>
                        </div>
                        <div class="center m-a-2">
                            <div class="icon-block img-circle">
                                <a href="#adbirt-login-form"><i class="material-icons md-36 text-muted">lock</i></a>
                            </div>
                        </div>
                    </div>

                    <div class="card bg-transparent">
                        <div class="card-header bg-white center">
                            <h4 class="card-title">Login</h4>
                            <p class="card-subtitle">Access your Adbirt Publisher Account</p>

                            <?php
                            foreach ($alertMessages as $key => $alert) {
                                $message = $alert['message'];
                                $severity = $alert['severity'];
                            ?>
                                <div class="alert alert-<?php echo $severity; ?>" role="alert">
                                    <?php echo $message; ?>
                                </div>
                            <?php
                            }
                            ?>

                        </div>

                        <div class="p-2">
                            <form method="post" id="adbirt-login-form">
                                <div class="form-group">
                                    <input type="text" name="email" id="email" class="form-control" autofocus placeholder="Email Address or Phone Number" required />
                                </div>

                                <div class="form-group">
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Password" required />
                                </div>

                                <div class="form-group ">
                                    <button type="submit" class="btn btn-primary btn-block btn-rounded">Login</button>
                                    <br />
                                </div>
                            </form>

                        </div>

                        <div class="card-footer center bg-white">
                            <p>Not yet a User? <a target="_blank" href="https://adbirt.com/register" class="text-center">Sign up</a></p>
                            <p>Or, go to <a target="_blank" href="https://adbirt.com/">Adbirt Home Page</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- end login section -->
    <?php
    }
    ?>

    <!-- begin script section -->
    <script src="https://adbirt.com/public/dist/vendor/jquery.min.js"></script>
    <script src="https://adbirt.com/public/dist/vendor/tether.min.js"></script>
    <script src="https://adbirt.com/public/dist/vendor/bootstrap.min.js"></script>
    <script src="https://adbirt.com/public/dist/vendor/adminplus.js"></script>
    <script src="https://adbirt.com/public/dist/js/main.min.js"></script>
    <script src="https://adbirt.com/public/dist/vendor/sweetalert.min.js"></script>
    <script src="https://adbirt.com/public/plugins/iCheck/icheck.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.8/dist/clipboard.min.js"></script>
    <script>
        new ClipboardJS('.copy-btn');
    </script>
    <script>
        $(function() {
            $('input').iCheck({
                checkboxClass: 'icheckbox_square-blue',
                radioClass: 'iradio_square-blue',
                increaseArea: '20%'
            });
        });
    </script>
    <script defer src="https://use.fontawesome.com/releases/v5.15.4/js/all.js" integrity="sha384-rOA1PnstxnOBLzCLMcre8ybwbTmemjzdNlILg8O7z1lUkLXozs4DHonlDtnE7fpc" crossorigin="anonymous"></script>
    <!-- end script section -->
</div>
<!-- end wrap -->
<?php
echo ob_get_clean();

update_option('adbirt_publisher_config', $config);
