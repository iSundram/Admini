package InstallerRhel;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.

use strict;
use warnings;

use CpanelLogger;

use CpanelMySQL ();

use Installer ();
our @ISA = qw/Installer/;

sub distro_type { return 'rhel' }

sub check_system_support {
    my ($self) = @_;

    $self->SUPER::check_system_support;

    $self->validate_distro_release_rpm;

    my $distro_name  = $self->distro_name;
    my $distro_major = $self->distro_major;

    if ( $distro_name eq 'rhel' ) {
        ( grep { $distro_major == $_ } qw/7/ ) or $self->invalid_system( "cPanel, L.L.C. does not support " . _distro_name($distro_name) . " version $distro_major." );
    }
    elsif ( $distro_name eq 'centos' ) {
        ( grep { $distro_major == $_ } qw/7/ ) or $self->invalid_system( "cPanel, L.L.C. does not support " . _distro_name($distro_name) . " version $distro_major." );
    }
    elsif ( $distro_name eq 'cloudlinux' ) {
        ( grep { $distro_major == $_ } qw/7 8 9/ ) or $self->invalid_system( "cPanel, L.L.C. does not support " . _distro_name($distro_name) . " version $distro_major." );
    }
    elsif ( $distro_name eq 'almalinux' ) {
        ( grep { $distro_major == $_ } qw/8 9/ ) or $self->invalid_system( "cPanel, L.L.C. does not support " . _distro_name($distro_name) . " version $distro_major." );
    }
    elsif ( $distro_name eq 'rocky' ) {
        ( grep { $distro_major == $_ } qw/8 9/ ) or $self->invalid_system( "cPanel, L.L.C. does not support " . _distro_name($distro_name) . " version $distro_major." );
    }
    else {
        return $self->invalid_system( "cPanel, L.L.C. does not support " . _distro_name($distro_name) . " for new installations." );
    }

    $self->validate_cloudlinux_registration;
    $self->validate_rhn_registration;

    return;
}

sub validate_distro_release_rpm {
    my ($self) = @_;

    my $distro_release = '/etc/redhat-release';

    -e $distro_release or $self->invalid_system("The system could not detect a valid release file for this distribution");

    chomp( my $release_rpm = `/bin/rpm -qf $distro_release` );    ## no critic(ProhibitQxAndBackticks)

    $release_rpm or $self->invalid_system("RPMs do not manage release file.");

    return;
}

sub _distro_name {
    my ( $distro, $full ) = @_;

    for my $names (
        [ 'centos',     'CentOS',      'CentOS' ],
        [ 'rhel',       'Red Hat',     'Red Hat Enterprise Linux®' ],
        [ 'cloudlinux', 'CloudLinux',  'CloudLinux™' ],
        [ 'almalinux',  'AlmaLinux',   'AlmaLinux' ],                   # based on how it is written at almalinux.org
        [ 'rocky',      'Rocky Linux', 'Rocky Linux' ],
    ) {
        return $names->[ $full ? 2 : 1 ] if $distro eq $names->[0];
    }
    return $distro;
}

sub validate_cloudlinux_registration {
    my ($self) = @_;

    # Short here if not CloudLinux.
    my $distro_name = $self->distro_name;
    return unless $distro_name eq 'cloudlinux';

    $self->validate_registration(
        {
            distro_name      => _distro_name($distro_name),
            full_distro_name => _distro_name( $distro_name, 1 ),
            register_command => '/usr/sbin/clnreg_ks --force',
        }
    );

    return;
}

sub validate_rhn_registration {
    my ($self) = @_;

    # Short here if not redhat
    my $distro_name = $self->distro_name;
    return unless $distro_name eq 'rhel';

    $self->validate_registration(
        {
            distro_name      => _distro_name($distro_name),
            full_distro_name => _distro_name( $distro_name, 1 ),
            register_command => '/usr/sbin/rhn_register',
        }
    );

    my @channels = `/usr/bin/yum repolist enabled`;    ## no critic(ProhibitQxAndBackticks)
    INFO("Validating that the system subscribed to the optional RHN channel...");

    # optional channel validated.
    return if grep { m/-optional(?:-[0-9]|-rpms|\/7Server)/ } @channels;

    my $optional_channel;
    foreach my $channel (@channels) {
        chomp $channel;

        # On RHEL 6, this line looks like this:
        # rhel-6-server-rpms                   Red Hat Enterprise Linux 6 Server (RPMs)
        # On RHEL 7, it looks like this:
        # rhel-7-server-rpms/7Server/x86_64                       Red Hat Enterprise Linux 7 Server (RPMs)                                  13,357
        next if ( $channel !~ /^[!*]?(rhel-([0-9xi_]+)-server-([0-9]+|rpms))[\s\/]+.*$/i );
        $channel          = $1;
        $optional_channel = $channel;
        $optional_channel =~ s/-server-6/-server-optional-6/;
        $optional_channel =~ s/-server-rpms/-server-optional-rpms/;
    }
    if ( !$optional_channel ) {
        ERROR("The server is not registered with a known Red Hat base channel.");
        ERROR('$> /usr/bin/yum repolist enabled');
        ERROR(`/usr/bin/yum repolist enabled`);    ## no critic(ProhibitQxAndBackticks)
        exit 8;                                    ## no critic(NoExitsFromSubroutines) # has always been like this
    }

    my $distro_major = $self->distro_major;
    ERROR("cPanel & WHM requires you to subscribe to the RHEL $distro_major optional channel, to get all of the needed packages.");
    ERROR("cPanel & WHM will not function without this channel. Check your subscriptions and then rerun the installer.");
    ERROR(" ");
    ERROR("Please run the following command: /usr/sbin/spacewalk-channel --add --channel=$optional_channel");
    ERROR("Or, for newer versions, run the following command: /usr/sbin/subscription-manager attach --auto");
    ERROR(" ");
    ERROR("You can register to the optional channel at http://rhn.redhat.com.");
    FATAL("Terminating...");
    return;
}

sub validate_registration {
    my ( $self, $opts ) = @_;

    INFO("Checking the $opts->{'distro_name'} registration for updates...");
    local $ENV{'TERM'} = 'dumb';
    my $registered = `/usr/bin/yum list < /dev/null 2>&1`;    ## no critic(ProhibitQxAndBackticks)

    if ( $registered =~ m/not register|Please run rhn_register/ms && $registered !~ /is receiving updates/ms ) {
        ERROR("When you use $opts->{'full_distro_name'}, you must register ");
        ERROR("with the $opts->{'distro_name'} Network before you install cPanel & WHM.");
        ERROR("Run the following command to register your server: $opts->{'register_command'} ");
        FATAL("The installation process will now terminate...");
    }
    return;
}

sub check_networking_scripts {
    my ($self) = @_;

    # Ensure network-scripts is installed and configured while networkManager
    # is disabled on CentOS 8 & 9.
    if ( $self->distro_major >= 8 ) {
        $self->check_network_scripts;
    }
    else {

        # Validate NetworkManager is off and uninstalled.
        $self->check_network_manager;
    }

    return;
}

# Install network-scripts package on CentOS 8 before we do the Network Manager check, so if
# a user changes the hostname and removes NM without installing network-scripts manually, the
# server will be able to come back online.
sub check_network_scripts {
    my ($self) = @_;

    return if $self->distro_major >= 9;

    INFO("Configuring networking now...");

    $self->yum_nohang_ssystem(qw{ /usr/bin/yum -y install network-scripts });
    Common::ssystem(qw{systemctl enable network.service});
    Common::ssystem(qw{systemctl start network.service});

    # These will fail if NetworkManager is not installed (i.e. CL8)
    # so we ignore the errors here in case NetworkManager is not installed
    Common::ssystem( qw{systemctl disable NetworkManager}, { ignore_errors => 1 } );
    Common::ssystem( qw{systemctl stop NetworkManager},    { ignore_errors => 1 } );

    return;
}

sub check_network_manager {
    my ($self) = @_;

    INFO("Checking for NetworkManager now...");

    if ( $self->distro_major == 6 ) {
        $self->check_initd_network_manager();
        return;
    }

    $self->check_systemd_network_manager();
    return;
}

sub network_manager_report_status {
    my ( $self, $uninstalled, $running, $startup ) = @_;

    if ($uninstalled) {
        INFO("NetworkManager is not installed.");
    }
    elsif ( $running || $startup ) {
        ERROR("********************* ERROR *********************");
        ERROR("NetworkManager is installed and running, or      ");
        ERROR("configured to startup.                           ");
        ERROR("");
        ERROR("cPanel does not support NetworkManager enabled   ");
        ERROR("systems.  The installation cannot proceed.       ");
        ERROR("");
        ERROR("See https://go.cpanel.net/disablenm for more     ");
        ERROR("information on disabling Network Manager.        ");
        ERROR("********************* ERROR *********************");
        ( $self->force ) ? WARN("Continuing installation due to force flag...") : FATAL("Exiting...");
    }
    else {
        WARN("NetworkManager is installed, but not active.  Consider removing it.");
    }
    return;
}

sub check_initd_network_manager {
    my ($self) = @_;

    my $status      = `service NetworkManager status 2>/dev/null`;
    my $uninstalled = !$status;
    my $running;
    my $startup;

    if ($status) {
        my ( $status_service, $verb, $status_state ) = split( ' ', $status );
        $running = $status_state ne 'stopped';
    }

    my $config = `chkconfig NetworkManager --list 2>/dev/null`;
    if ($config) {
        my ( $config_service, $config_runlevels ) = split( ' ', $config, 2 );
        $startup = $config_runlevels =~ m/:on/;
    }

    $self->network_manager_report_status( $uninstalled, $running, $startup );
    return;
}

sub check_systemd_network_manager {
    my ($self) = @_;

    my $status      = `systemctl --all --no-legend --no-pager list-units NetworkManager.service 2>/dev/null`;
    my $uninstalled = !$status;
    my $running;
    my $startup;

    if ($status) {
        my ( $status_service, $load_state, $active_state, $sub_state, @service_description ) = split( ' ', $status );
        $running = $active_state && $sub_state && $active_state ne 'inactive' && $sub_state ne 'dead';

        # they uninstalled it, but didn't run systemctl daemon-reload
        if ( $load_state eq 'not-found' ) {
            $uninstalled = 1;
        }
    }

    my $config = `systemctl --all --no-legend --no-pager list-unit-files NetworkManager.service 2>/dev/null`;
    if ($config) {
        my ( $config_service, $enabled_state ) = split( ' ', $config );
        $startup = $enabled_state && $enabled_state ne 'disabled' && $enabled_state ne 'masked';
    }

    $self->network_manager_report_status( $uninstalled, $running, $startup );
    return;
}

sub check_system_files {
    my ($self) = @_;
    $self->SUPER::check_system_files;

    _ensure_selinux_disabled();

    # A tiny version of Cpanel::RpmUtils::checkupdatesystem();
    my $out = `/usr/bin/yum info glibc 2>&1` || '';    ## no critic(ProhibitQxAndBackticks)
    if ( $? != 0 ) {
        ERROR("Your operating system's RPM update method (yum) could not locate the glibc package. This is an indication of an improper setup. You must correct this error before you proceed. ");
        DEBUG("Output: $out");
        FATAL("\n\n");
    }

    return;
}

sub _ensure_selinux_disabled {

    # Disable selinux on RHEL systems.
    my $selinux_config_dir  = '/etc/selinux';
    my $selinux_config_file = "$selinux_config_dir/config";

    if ( open my $fh, '<', $selinux_config_file ) {
        local $/;
        my $selinux_config = <$fh>;
        my ($current_setting) = $selinux_config =~ m{^SELINUX=(\w+)$}xms;

        # If selinux config exists and is configured to disabled, do nothing.
        if ( $current_setting eq 'disabled' ) {
            return;
        }
    }

    INFO("Creating SELinux config file: $selinux_config_file");
    mkdir $selinux_config_dir unless -d $selinux_config_dir;
    open my $fh, '>', $selinux_config_file or FATAL("Unable to create $selinux_config_file");
    print {$fh} "SELINUX=disabled\n";
    close $fh;

    return;
}

# This is a peared down version of ensure_rpms_installed because we don't yet have cpanel code.
# We also assume centhat 5/6/7 for this code.
sub install_basic_precursor_packages {
    my ($self) = @_;

    # Disable rpmforge repos
    if ( glob '/etc/yum.repos.d/*rpmforge*' ) {
        WARN('DISABLING rpmforge yum repositories.');
        mkdir( '/etc/yum.repos.d.disabled', 0755 );
        Common::ssystem('mv -fv -- /etc/yum.repos.d/*rpmforge* /etc/yum.repos.d.disabled/ 2>/dev/null');
    }

    $self->setup_update_config();

    # No fastest-mirror package available on CentOS 8
    if ( is_yum_system() ) {
        $self->install_fastest_mirror;
    }

    # Minimal packages needed to use yum.

    INFO("Installing packages needed to download and run the cPanel initial install.");

    # Assure wget/bzip2/gpg are installed for centhat. These packages are needed prior to sysup
    # Password strength functionality relies on cracklib-dicts being installed
    my @packages_to_install = qw/wget bzip2 gnupg2 xz yum nscd psmisc cracklib-dicts crontabs sysstat perl-Net-SSLeay/;

    my $distro_major = $self->distro_major;
    if ( $distro_major >= 8 ) {

        # Install epel-releases manually since the BaseOS repo no longer contains this package
        my $repo = 'https://dl.fedoraproject.org/pub/epel/epel-release-latest-' . $distro_major . '.noarch.rpm';
        $self->yum_nohang_ssystem( '/usr/bin/yum', '-y', 'install', $repo );

        # libssh2 moved to epel. python3 is required for retry_rpm.
        push @packages_to_install, 'python3';
    }
    else {
        # rdate and yum-fastestmirror aren't available on 8, but set as default for everything else
        push( @packages_to_install, 'rdate', 'yum-fastestmirror' );
    }

    # Don't attempt to install kernel-headers on systems with the CentOS Plus kernel headers already installed.
    # We do not need kernel-headers for v70+ since we don't link anything against the kernel anymore
    # if ( !has_kernel_plus_headers() ) {
    #    push @packages_to_install, 'kernel-headers';    # Needed because Cpanel::SysPkgs excludes kernel_version
    #}

    if (@packages_to_install) {
        $self->yum_nohang_ssystem( '/usr/bin/yum', '-y', 'install', @packages_to_install );
    }

    # Make sure all rpms are up to date if we are running
    # an older version that CentOS 7 since we only support
    # Centos 6.5+.
    #
    # Additionally we need Centos 7.4+ to ensure they
    # have the latest version of openssl per
    # CPANEL-25853

    my $distro_version = sprintf( "%d.%d", $self->distro_major, $self->distro_minor );
    if ( $distro_version < 6.5 || ( $distro_major == 7 && $distro_version < 7.4 ) ) {
        $self->yum_nohang_ssystem( '/usr/bin/yum', '-y', 'update' );
    }

    # Reinstate yum exclusions
    unlink '/etc/checkyumdisable';
    Common::ssystem('/usr/local/cpanel/scripts/checkyum') if ( -e '/usr/local/cpanel/scripts/checkyum' );
    return;
}

sub setup_update_config {
    my ($self) = @_;

    # legacy files
    unlink('/var/cpanel/useup2date');
    unlink('/var/cpanel/useyum');
    $self->touch('/var/cpanel/yum_rhn') if $self->distro_name eq 'redhat';

    INFO("The installation process will now ensure that GPG is set up properly before it imports keys.");
    Common::ssystem(qw{/usr/bin/gpg --list-keys});

    INFO("The installation process will now import GPG keys for yum.");
    if ( -e '/usr/share/rhn/RPM-GPG-KEY' ) {
        Common::ssystem( '/usr/bin/gpg', '--import', '/usr/share/rhn/RPM-GPG-KEY' );
        Common::ssystem( '/bin/rpm',     '--import', '/usr/share/rhn/RPM-GPG-KEY' );
    }

    if ( !-e '/etc/yum.conf' && -e '/etc/centos-yum.conf' ) {
        INFO("The system will now set up yum from the /etc/centos-yum.conf file.");
        Common::ssystem(qw{cp -f /etc/centos-yum.conf /etc/yum.conf});
    }
    return;
}

sub yum_nohang_ssystem {
    my ( $self, @cmd ) = @_;

    # some base packages were moved to PowerTools on CentOS 8; e.g. elinks
    my $power_repo = $self->_get_powertools_repoid;
    push @cmd, "--enablerepo=$power_repo" if $power_repo;

    my $failcount = 0;
    my $result    = 1;
    while ($result) {    # While yum is failing.
        $result = Common::ssystem(@cmd);
        last if ( !$result );    # yum came back clean. Stop re-trying

        $failcount++;
        if ( $failcount > 5 ) {
            FATAL("yum failed $failcount times. The installation process cannot continue.");
        }
    }
    return;
}

# see also: Cpanel::SysPkgs::YUM::_get_powertools_repoid
sub _get_powertools_repoid {
    my ($self) = @_;

    return if $self->distro_major <= 7;
    return if $self->distro_major >= 9 && $self->distro_name eq 'cloudlinux';

    return 'cloudlinux-PowerTools' if $self->distro_name eq 'cloudlinux';

    return 'crb' if $self->distro_major >= 9;

    if ( $self->distro_major == 8 && $self->distro_minor >= 3 ) {    # distro_ver >= 8.3
        return 'powertools';
    }

    return 'PowerTools';
}

sub is_yum_system {
    return 'yum' eq name_of_package_manager();
}

my $package_manager;

sub name_of_package_manager {
    return $package_manager if defined $package_manager;

    my $code = Common::ssystem( qw(rpm -q dnf), { 'ignore_errors' => 1 } );
    if ( 0 == $code ) {
        $package_manager = 'dnf';
    }
    else {
        $package_manager = 'yum';
    }

    return $package_manager;
}

# Install fastest mirror plugin for CentOS
sub install_fastest_mirror {
    my ($self) = @_;

    return unless $self->distro_name =~ m/centos|almalinux|rocky/;
    return if has_yum_plugin_fastestmirror();

    INFO("Installing the fastest mirror plugin...");
    Common::ssystem( '/usr/bin/yum', 'clean', 'plugins' );
    Common::ssystem( '/usr/bin/yum', '-y',    'install', 'yum-fastestmirror' );
    Common::ssystem( '/usr/bin/yum', 'clean', 'plugins' );

    # We set the number of threads in bootstrap
    #
    #
    #  We used to support 512MB of ram which caused a problem with a high
    #  maxthreads (FB-51412), however this is no longer an issue
    #  https://documentation.cpanel.net/display/78Docs/Installation+Guide+-+System+Requirements
    #
    return;
}

sub has_yum_plugin_fastestmirror {
    my $rpm_query = `rpm -q --nodigest --nosignature yum-plugin-fastestmirror`;    ## no critic(ProhibitQxAndBackticks)
    return $rpm_query =~ /not installed/ ? 0 : 1;
}

sub remove_distro_software {
    my ($self) = @_;

    my @remove_rpms = qw(
      dovecot
      exim
      mysql
      MySQL
      mysql-max
      MySQL-Max
      mysql-devel
      MySQL-devel
      mysql-client
      MySQL-client
      mysql-ndb-storage
      MySQL-ndb-storage
      mysql-ndb-management
      MySQL-ndb-management
      mysql-ndb-tools
      MySQL-ndb-tools
      mysql-ndb-extra
      MySQL-ndb-extra
      mysql-shared
      MySQL-shared
      mysql-libs
      MySQL-libs
      mysql-bench
      MySQL-bench
      mysql-server
      MySQL-server
      wu-ftpd
      portreserve
      postfix
      sendmail
      smail
      spamassassin
      apache-conf
      mod_perl
      mariadb-libs
      MariaDB-client
      MariaDB-common
      MariaDB-server
      MariaDB-compat
      MariaDB-shared
      pure-ftpd
      proftpd
      bind-chroot
    );

    INFO('Ensuring that prelink is disabled...');

    if ( open( my $fh, '+<', '/etc/sysconfig/prelink' ) ) {
        my @lines = map { my $s = $_; $s =~ s/^(PRELINKING=)yes(.*)$/$1no$2/; $s } <$fh>;
        seek( $fh, 0, 0 );
        print {$fh} @lines;
        truncate( $fh, tell($fh) );
    }

    INFO('Ensuring that conflicting services are not installed...');
    my @rpms_to_remove =
      map  { ( split( m{-}, $_, 2 ) )[1] }                                                                   # split INSTALLED-NAME and take NAME
      grep { rindex( $_, 'INSTALLED-', 0 ) == 0 }                                                            # Only output that starts with INSTALLED- is installed
      split( m{\n}, `rpm -q --nodigest --nosignature --queryformat 'INSTALLED-%{NAME}\n' @remove_rpms` );    ## no critic(ProhibitQxAndBackticks)
    if (@rpms_to_remove) {
        DEBUG(" Removing @rpms_to_remove...");
        Common::ssystem( 'rpm', '-e', '--nodeps', @rpms_to_remove, { ignore_errors => 1 } );
    }

    INFO('Removing conflicting service references from the RPM database (but leaving the services installed)...');
    my @all_pkgs = `rpm -qa --nodigest --nosignature --queryformat '%{name}\n'`;                             ## no critic(ProhibitQxAndBackticks)
    @all_pkgs = grep { $_ !~ m/^(?:cpanel|alt)-/ } @all_pkgs;                                                # Don't worry about cpanel or cloudlinux RPMS.

    unless ( $self->skip_apache ) {
        foreach my $rpm ( grep m/http|php|apache|mod_perl/, @all_pkgs ) {
            next if $rpm =~ m/^(perl|lib)/;                                                                  # ignore things like libnghttp2 and perl-LWP-Protocol-https which we want!!!
            chomp $rpm;
            DEBUG(" Removing $rpm...");
            Common::ssystem( 'rpm', '-e', '--nodeps', $rpm, { ignore_errors => 1 } );
        }
    }

    # CPANEL-30184: Required since the file is not managed by the rpm, and is not removed with it.
    if ( -e '/var/lib/dovecot/ssl-parameters.dat' ) {
        unlink '/var/lib/dovecot/ssl-parameters.dat';
    }

    return;
}

sub verify_mysql_version {
    my ( $self, $cpanel_config ) = @_;

    my $mysql_version = $cpanel_config->{'mysql-version'};

    return unless length $mysql_version;

    # The only supported installable versions are:
    # 5.7, 8.0, 8.4, 10.3, 10.5, 10.6, 10.11 11.4
    # The following will recommend a specific version in error output:
    # 5.5, 5.6, 10.0, 10.1, 10.2
    my $version_filter = qr{^(?:
        5\.[5-7]
        | 8(?:\.[04])
        | 10(?:\.(?:[0-3,5-6]|11))
        | 11(?:\.(?:[4]))
    )$}x;
    unless ( $mysql_version =~ $version_filter ) {
        FATAL('The mysql-version value in /root/cpanel_profile/cpanel.config is either invalid or references an unsupported MySQL/MariaDB version. See https://go.cpanel.net/supported-mysql-mariadb-versions for a list of supported versions.');
    }

    my $lts_version  = $self->lts_version;
    my $distro_major = $self->distro_major;

    my %db_id = CpanelMySQL::get_db_identifiers($mysql_version);

    if ( $self->distro_name eq 'cloudlinux' && $distro_major == 6 && $mysql_version >= 10.5 ) {
        FATAL("cPanel & WHM does not support $db_id{'stylized'} $mysql_version on CloudLinux™ $distro_major. See https://go.cpanel.net/supported-mysql-mariadb-versions for a list of supported versions.");
    }

    my $advice = CpanelMySQL::get_db_version_advice( $mysql_version, $lts_version, $distro_major, $db_id{'plain'} );

    if ( $advice->{ver} && $advice->{action} ) {
        FATAL('The mysql-version value in /root/cpanel_profile/cpanel.config is either invalid or references an unsupported MySQL/MariaDB version. See https://go.cpanel.net/supported-mysql-mariadb-versions for a list of supported versions.');
    }

    return;
}

sub is_python2_named_python {
    return 0 == Common::ssystem( qw(yum info python), { 'ignore_errors' => 1, 'quiet' => 1 } );
}

# These packages are needed for MySQL later in the install
# By installing them now we do not have to wait for
# download
sub background_download_packages_used_during_initial_install {
    my ($self) = @_;

    my @elinks_deps                       = qw(js nss_compat_ossl);
    my @sysup_packages_to_install         = qw{quota expat expat-devel};
    my @ea4_packages_to_install           = qw{elinks libssh2 libssh2-devel libvpx scl-utils perl-libwww-perl krb5-devel perl-Compress-Raw-Bzip2 perl-Compress-Raw-Zlib autoconf automake};
    my @mysql_support_packages_to_install = qw{numactl-libs grep shadow-utils coreutils perl-DBI};

    @ea4_packages_to_install = grep { $_ ne 'elinks' } @ea4_packages_to_install if $self->distro_major >= 9;

    my @packages_to_install = ( @mysql_support_packages_to_install, @sysup_packages_to_install, @ea4_packages_to_install );

    if ( $self->distro_major <= 8 ) {    # python2 is not available starting with 9
        my @python2_packages = qw{python python-devel python-docs python-setuptools };

        if ( is_python2_named_python() ) {
            push @packages_to_install, @python2_packages;
        }
        else {
            push @packages_to_install, map { my $pkg = $_; $pkg =~ s/python/python2/; $pkg } @python2_packages;
        }
    }

    if ( $self->distro_major < 8 ) {
        push @packages_to_install, @elinks_deps;
    }

    my @epel;
    if ( -e "/etc/yum.repos.d/epel.repo" ) {    # for ea4
        @epel = ('--enablerepo=epel');
    }

    return $self->run_in_background( sub { $self->yum_nohang_ssystem( '/usr/bin/yum', @epel, '--downloadonly', '-y', 'install', @packages_to_install ); } );
}

sub pre_checks_while_waiting_for_fix_cpanel_perl {
    my ($self) = @_;

    $self->SUPER::pre_checks_while_waiting_for_fix_cpanel_perl;

    Common::ssystem( '/usr/sbin/setenforce', '0', { ignore_errors => 1 } ) if -e '/usr/sbin/setenforce';

    return;
}

# Attempt to enable module for dnf to specific version
sub enable_dnf_module {
    my ( $self, $module, $version ) = @_;
    if ( !$module || !$version ) {
        WARN("Invalid call to enable_dnf_module(“$module”,“$version“)");
    }
    my $result = Common::ssystem( '/usr/bin/dnf', 'module', 'enable', "$module:$version", '-y' );
    return;
}
1;
