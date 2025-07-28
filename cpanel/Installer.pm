package Installer;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.

use strict;
use warnings;

use Getopt::Long ();
use POSIX        ();
use Errno        qw(EAGAIN);

use CpanelLogger;

use Common       ();
use CpanelGPG    ();
use CpanelConfig ();
use OSDetect     ();

use InstallerRhel   ();
use InstallerUbuntu ();

use constant LOCK_FILE                        => '/root/installer.lock';
use constant PRODUCT_DNSONLY                  => 1 << 6;
use constant PRODUCT_WP2                      => 1 << 20;
use constant MINIMUM_CPANEL_VERSION_SUPPORTED => 117;                      # TODO Kill/Update InstallerUbuntu::CPANEL_UBUNTU_SUPPORT entries and MINIMUM_CUSTOM_OS_VERSION once this LTS bumps.

# CloudLinux 6 and CentOS/CloudLinux 7 support are removed starting with 112.
use constant OS_MAXIMUM_VERSIONS => {
    'centos6'  => 110,
    'centos7'  => 110,
    'ubuntu20' => 118,
};

use constant NO_REBOOT_TOUCH_FILE => '/etc/no-reboot-after-installation';

sub new {
    my $common_class = -e '/etc/debian_version' ? 'InstallerUbuntu' :    #
      -e '/etc/redhat-release' ? 'InstallerRhel' :                       #
      die("Unknown distro");

    my $self = bless {}, $common_class;

    return $self;
}

sub setup {
    my ( $self, @args ) = @_;

    if ( $] < 5.010 ) {
        print "This installer requires Perl 5.10.0 or better.\n";
        die "Cannot continue.\n";
    }

    $self->{'parent_proc'} = $$;

    $self->set_globals;

    $self->{'install_start'} = CpanelLogger::open_logs();

    $self->parse_argv(@args);

    $self->get_script_lock;
    $self->detect_distro;

    return;
}

sub set_globals {
    $ENV{'CPANEL_BASE_INSTALL'}                         = 1;
    @ENV{qw{LANG LANGUAGE LC_ALL LC_MESSAGES LC_CTYPE}} = qw{C C C C C};
    $ENV{'DEBIAN_FRONTEND'}                             = 'noninteractive';

    delete $ENV{'LANGUAGE'};

    $| = 1;    ## no critic qw(RequireLocalizedPunctuationVars)
    umask 022;

    return;
}

sub force                 { return shift->{'options'}->{'force'} }
sub skip_apache           { return shift->{'options'}->{'skip_apache'} }
sub skip_repo_setup       { return shift->{'options'}->{'skip_repo_setup'} }
sub skip_license_check    { return shift->{'options'}->{'skip_license_check'} }
sub skip_cloudlinux       { return shift->{'options'}->{'skip-cloudlinux'} }
sub skip_imunifyav        { return shift->{'options'}->{'skip-imunifyav'} }
sub skip_imunify360       { return shift->{'options'}->{'skip-imunify360'} }
sub skip_wptoolkit        { return shift->{'options'}->{'skip-wptoolkit'} }
sub stop_at_update_now    { return shift->{'options'}->{'stop_at_update_now'} }
sub stop_after_update_now { return shift->{'options'}->{'stop_after_update_now'} }
sub no_reboot             { return shift->{'options'}->{'no-reboot'} }
sub install_start         { return shift->{'install_start'} }

sub parse_argv {
    my ( $self, @args ) = @_;

    DEBUG("Parsing command line arguments.");

    # Defaults. Look for touch files.
    $self->{options} = {
        'force'                 => 0,
        'skip-cloudlinux'       => 0,
        'skip-imunifyav'        => 0,
        'skip-imunify360'       => 0,
        'skip-wptoolkit'        => 0,
        'skip_apache'           => -e '/root/skipapache' ? 1 : 0,
        'skip_repo_setup'       => 0,
        'skip_license_check'    => 0,
        'stop_at_update_now'    => 0,
        'stop_after_update_now' => 0,
        'experimental-os'       => undef,
        'source'                => undef,
        'tier'                  => undef,
        'myip'                  => undef,
        'no-reboot'             => -e '/etc/no-reboot-after-installation' ? 1 : 0,
    };

    # Parse args.
    Getopt::Long::GetOptionsFromArray(
        \@args,
        'force'                               => \$self->{options}->{'force'},
        'skip-cloudlinux'                     => \$self->{options}->{'skip-cloudlinux'},
        'skip-imunifyav'                      => \$self->{options}->{'skip-imunifyav'},
        'skip-imunify360'                     => \$self->{options}->{'skip-imunify360'},
        'skip-wptoolkit'                      => \$self->{options}->{'skip-wptoolkit'},
        'skipapache|skip-apache'              => \$self->{options}->{'skip_apache'},
        'skipreposetup|skip-repo-setup'       => \$self->{options}->{'skip_repo_setup'},
        'skiplicensecheck|skip-license-check' => \$self->{options}->{'skip_license_check'},
        'stop_at_update_now'                  => \$self->{options}->{'stop_at_update_now'},
        'stop_after_update_now'               => \$self->{options}->{'stop_after_update_now'},
        'experimental-os=s'                   => \$self->{options}->{'experimental-os'},
        'source=s'                            => \$self->{options}->{'source'},
        'tier=s'                              => \$self->{options}->{'tier'},
        'myip=s'                              => \$self->{options}->{'myip'},
        'no-reboot'                           => \$self->{options}->{'no-reboot'},

        'skip-all-imunify' => sub {
            $self->{options}->{'skip-imunifyav'} = $self->{options}->{'skip-imunify360'} = 1;
        },
    );

    # We must *search* for args unprefixed with -- here for product type
    # "because that's the way it's always been passed in" unfortunately.
    # Would have been simpler as a grep, but we only want to take "something
    # valid looking" beginning at index -1.
    my $type = 'standard';
    foreach my $arg ( reverse @args ) {
        if ( grep { $_ eq $arg } qw{standard dnsonly wp2} ) {
            $type = $arg;
            last;
        }
    }

    # Set product, tier and source for install in CpanelConfig
    CpanelConfig::product($type);
    CpanelConfig::tier( $self->{options}{tier} )     if $self->{options}{tier};
    CpanelConfig::source( $self->{options}{source} ) if $self->{options}{source};
    CpanelConfig::myip_url( $self->{options}{myip} ) if $self->{options}{myip};

    $type =~ s/wp2/WP Squared/;
    INFO("Install type: $type\n");

    $self->create_touch_files;

    return;
}

*is_wp2     = \&CpanelConfig::is_wp2;
*is_dnsonly = \&CpanelConfig::is_dnsonly;

sub distro_type  { die 'unimplemented' }
sub distro_name  { return shift->{'os'}->{'distro'} || die }    # Should always be populated on call.
sub distro_major { return shift->{'os'}->{'major'}  || die }    # Should always be populated on call.

sub product_name {
    my %display_name_map = (
        'dnsonly'  => 'DNSONLY',
        'wp2'      => 'WP Squared',
        'standard' => 'cPanel & WHM',
    );
    return $display_name_map{ CpanelConfig::product() };
}

sub distro_minor {
    my $minor = shift->{'os'}->{'minor'};
    die q[minor version not defined] unless defined $minor;
    return $minor;
}

sub detect_distro {
    my ($self) = @_;
    my @os_info = OSDetect::get_os_info();

    $self->{'os'}->{'kernel'} = shift @os_info;
    $self->{'os'}->{'distro'} = shift @os_info;
    $self->{'os'}->{'major'}  = shift @os_info;
    $self->{'os'}->{'minor'}  = shift @os_info;

    return;
}

sub cpanel_version {
    my ($self) = @_;

    return $self->{'cpanel_version'} if $self->{'cpanel_version'};

    my $v = CpanelConfig::get_cpanel_version();
    INFO("Using version '$v'");

    return $self->{'cpanel_version'} = $v;
}

sub lts_version {
    my ($self) = @_;

    return $self->{'lts_version'} if $self->{'lts_version'};

    my $v = $self->cpanel_version;
    return $self->{'lts_version'} = CpanelConfig::get_lts_version($v);
}

sub create_touch_files {
    my ($self) = @_;

    ensure_var_cpanel();

    if ( $self->is_dnsonly ) {
        INFO("cPanel DNSONLY installation requested.");
        FATAL("Existing cPanel license file detected! Aborting DNSONLY installation.") if -s '/usr/local/cpanel/cpanel.lisc';
        $self->touch('/var/cpanel/dnsonly');
        $self->touch('/var/cpanel/noimunifyav');
        $self->touch('/var/cpanel/nowptoolkit');
    }
    else {
        unlink '/var/cpanel/dnsonly';    # Just in case the customer ran with the wrong args previously.
    }

    if ( $self->is_wp2 ) {
        INFO( "Setting up a " . $self->product_name . " installation" );
    }

    if ( $self->skip_cloudlinux ) {
        INFO("Skip cloudlinux installation requested.");
        $self->touch('/var/cpanel/nocloudlinux');
    }

    if ( $self->skip_imunifyav ) {
        INFO("Skip imunifyav installation requested.");
        $self->touch('/var/cpanel/noimunifyav');
    }

    if ( $self->skip_imunify360 ) {
        INFO("Skip imunify360 installation requested.");
        $self->touch('/var/cpanel/noimunify360');
    }

    if ( $self->skip_wptoolkit ) {
        INFO("Skip WordPress Toolkit installation requested.");
        $self->touch('/var/cpanel/nowptoolkit');
    }

    if ( $self->no_reboot ) {
        INFO("Automatic reboot disabled as requested.");
        $self->touch(NO_REBOOT_TOUCH_FILE);
    }

    # --experimental-os=almalinux-9.0
    $self->setup_experimental_os( $self->{options}->{'experimental-os'} );

    return;
}

# The customer ran latest --experimental-os=centos-7.2
sub setup_experimental_os {
    my ( $self, $settings ) = @_;

    defined $settings or return;    # Didn't pass this option on command line.

    my @os_info = $settings =~ m{^([^-]+)-([0-9]+)\.([0-9]+)}    #
      or die("Unrecognized --experimental-os option. Try: --experimental-os=almalinux-9.0");
    unshift @os_info, $^O;
    push @os_info, '2020';                                       # An arbitrary build ID we're going to push on the end for consistency.

    my ( $os, $distro, $major, $minor, $build ) = @os_info;

    if ( $distro && $distro eq 'cloudlinux' ) {
        FATAL("Using --experimental-os is not permitted for CloudLinux.");
    }

    mkdir '/var/cpanel/caches', 0711;

    OSDetect::clear( custom => 1 );

    WARN( <<"EOS" );
--experimental-os=$settings was successful.
You are currently installing cPanel & WHM on an unsupported distribution.
We discourage you from using this server for production purposes.
EOS

    # Write out the cache file.
    local $!;
    symlink "$os|$distro|$major|$minor|$build", OSDetect::CACHE_FILE;

    # Write out the lock file to prevent the cache from being updated going forward.
    symlink "1", OSDetect::CACHE_FILE_CUSTOM;

    return;
}

sub get_script_lock {
    my ($self) = @_;

    if ( open my $fh, '<', LOCK_FILE ) {
        print "The system detected an installer lock file: (" . LOCK_FILE . ")\n";
        print "Make certain that an installer is not already running.\n\n";
        print "You can remove this file and re-run the cPanel installation process after you are certain that another installation is not already in progress.\n\n";
        my $pid = <$fh>;
        if ($pid) {
            chomp $pid;
            print `ps auxwww |grep $pid`;    ## no critic(ProhibitQxAndBackticks)
        }
        else {
            print "Warning: The system could not find pid information in the " . LOCK_FILE . " file.\n";
        }
        return 1;
    }

    # Create the lock file.
    if ( open my $fh, '>', LOCK_FILE ) {
        print {$fh} "$$\n";

        close $fh;
    }
    else {
        FATAL( "Unable to write lock file " . LOCK_FILE );

        return 1;
    }

    $self->{'original_pid'} = $$;

    return;
}

sub check_system_support {
    my ($self) = @_;

    $self->invalid_system( "Unsupported kernel (" . $self->{'os'}->{'kernel'} . ") for operating system" ) if $self->{'os'}->{'kernel'} ne 'linux';

    if ( $self->distro_major == 7 && !$self->force ) {
        $self->invalid_system( "Unsupported operating system (" . $self->{'os'}->{'distro'} . " " . $self->{'os'}->{'major'} . ")" );
    }

    my @uname = POSIX::uname();
    if ( $uname[4] ne 'x86_64' ) {
        $self->invalid_system("cPanel & WHM supports 64-bit versions (not $uname[4]) only.");
    }

    INFO("Checking RAM now...");
    my $total_memory = $self->get_total_memory();
    my $minmemory    = 2_048;

    if ( $total_memory < $minmemory ) {
        ERROR("cPanel & WHM requires a minimum of $minmemory MB of available RAM for your operating system. Your system only has $total_memory MB available. We recommend that you increase the total RAM on your system.");
        FATAL("Increase the server's total amount of RAM, and then reinstall cPanel & WHM.");
    }
    return;
}

sub get_kexec_crash_size {
    my ($self) = @_;

    my $file = '/sys/kernel/kexec_crash_size';
    my $size = 0;

    if ( -s $file && open( my $fh, "<", $file, ) ) {
        $size += <$fh>;
        close($fh);
    }
    else {
        WARN("The installer could not read the file $file. Please ensure that the file exists and that you have the correct permissions.");
    }

    if ($size) {
        WARN(
            'The installer has detected that kernel crash dumping is configured on your system. This means that a portion of system memory is permanently reserved for a capture kernel and is inaccessible to the main kernel. If you encounter memory-related issues during or after installation, they may be resolved by reducing or altogether removing the crashkernel boot loader configuration option.'
        );
    }

    return $size;
}

sub get_total_memory {
    my ($self) = @_;

    # Tests on different architectures show that 15% is safe.
    # This tolerance is expanded to 25% in instances where kdump is active in order to accommodate hyperscalers.
    my $tolerance_factor = $self->get_kexec_crash_size() ? 1.25 : 1.15;

    # MemTotal: Total usable ram (i.e. physical ram minus a few reserved
    #          bits and the kernel binary code)
    # note, another option would be to use "dmidecode --type 17", or dmesg
    #   but this will require an additional RPM
    #   we just want to be sure that a customer does not install
    #   with 512 when 700 or more is required
    my $meminfo = q{/proc/meminfo};
    if ( open( my $fh, "<", $meminfo ) ) {
        while ( my $line = readline $fh ) {
            if ( $line =~ m{^MemTotal:\s+([0-9]+)\s*kB}i ) {
                return int( int( $1 / 1_024 ) * $tolerance_factor );
            }
        }
    }

    return 0;    # something is wrong
}

sub invalid_system {
    my ( $self, $message ) = @_;
    $message ||= '';
    chomp $message;

    ERROR($message);
    ERROR('cPanel & WHM does not support the version of the Linux distribution you are running. You will need to install on one of the supported versions of a Linux distribution listed at https://go.cpanel.net/supported-os');
    FATAL('Please reinstall cPanel & WHM from a valid distribution.');
    return;
}

sub clean_install_check {

    INFO('Checking for any control panels...');
    my @server_detected;
    push @server_detected, 'DirectAdmin' if ( -e '/usr/local/directadmin' );
    push @server_detected, 'Plesk'       if ( -e '/etc/psa' );
    push @server_detected, 'Ensim'       if ( -e '/etc/appliance' || -d '/etc/virtualhosting' );

    #push @server_detected, 'Alabanza'    if ( -e '/etc/mail/mailertable' );
    push @server_detected, 'Zervex'              if ( -e '/var/db/dsm' );
    push @server_detected, 'Web Server Director' if ( -e '/bin/rpm' && `/bin/rpm -q ServerDirector` =~ /^ServerDirector/ms );    ## no critic(ProhibitQxAndBackticks)

    # Don't just check for /usr/local/cpanel, as some people will have created
    # that directory as a mount point for the install.
    push @server_detected, 'cPanel & WHM' if -e '/usr/local/cpanel/cpkeyclt';

    return if ( !@server_detected );

    ERROR("The installation process found evidence that the following control panels were installed on this server:");
    ERROR($_) foreach (@server_detected);
    FATAL('You must install cPanel & WHM on a clean server.');
    return;
}

sub check_system_files {
    INFO("Checking for essential system files...");

    unless ( -f '/etc/fstab' ) {
        ERROR("Your system is missing the file /etc/fstab.  This is an");
        ERROR("essential system file that is part of the base system.");
        FATAL("Please ensure the system has been properly installed.");
    }

    setup_empty_directories();

    setup_custom_cpanel_config();

    assure_nobody();

    return;
}

# Place customer provided cpanel.config in place early in case we need to block on any of the settings.
sub setup_custom_cpanel_config {
    my $custom_cpanel_config_file = '/root/cpanel_profile/cpanel.config';
    if ( -e $custom_cpanel_config_file ) {
        INFO("The system is placing the custom cpanel.config file from $custom_cpanel_config_file.");
        unlink '/var/cpanel/cpanel.config';
        system( '/bin/cp', $custom_cpanel_config_file, '/var/cpanel/cpanel.config' );
    }
    return;
}

# mkdir some directories.
sub setup_empty_directories {
    INFO('The installation process will now set up the necessary empty cpanel directories.');

    ensure_var_cpanel();

    foreach my $dir (qw{/usr/local/cpanel /usr/local/cpanel/base /usr/local/cpanel/base/frontend /usr/local/cpanel/logs /var/cpanel/tmp /var/cpanel/version /var/cpanel/perl /var/named}) {
        unlink $dir if ( -f $dir || -l $dir );

        if ( !-d $dir ) {
            DEBUG("mkdir $dir");
            mkdir( $dir, 0755 );
        }
    }

    foreach my $dir (qw{/var/cpanel/logs}) {
        unlink $dir if ( -f $dir || -l $dir );

        if ( !-d $dir ) {
            DEBUG("mkdir $dir");
            mkdir( $dir, 0700 );
        }
    }

    ensure_feature_showcase_dir();

    return;
}

sub ensure_var_cpanel {

    my $dir   = '/var/cpanel';
    my $perms = 0711;

    if ( -f $dir || -l $dir ) {
        unlink $dir or FATAL("Fail to remove existing file $dir (should be a directory) $!");
    }

    if ( !-d $dir ) {
        mkdir $dir, $perms;
    }
    else {
        chown 0, 0, $dir;
        chmod $perms, $dir;
    }

    return;
}

sub ensure_feature_showcase_dir {

    foreach my $dir (qw{ /var/cpanel/activate /var/cpanel/activate/features }) {
        my $perms = 0700;

        if ( -f $dir || -l $dir ) {
            unlink $dir or FATAL("Fail to remove existing file $dir (should be a directory) $!");
        }

        if ( !-d $dir ) {
            mkdir $dir, $perms;
        }

        Common::ssystem( 'chown', '-R', 'root:root', $dir );
        Common::ssystem( 'chmod', '-R', '0700',      $dir );
    }

    return;
}

sub disable_systemd_resolved_if_enabled { return }    # Only run on ubuntu systems.

sub setup_and_check_resolv_conf {

    # Remote resolvers are required, since we remove local BIND during installation.
    open my $resolv_conf_fh, '<', '/etc/resolv.conf' or FATAL("Could not open /etc/resolv.conf: $!");

    if ( !grep { m/^\s*nameserver\s+/ && !m/\s+127.0.0.1$/ } <$resolv_conf_fh> ) {
        FATAL("/etc/resolv.conf must be configured with non-local resolvers for installations to complete.");
    }

    INFO("Validating whether the system can look up domains...");
    my @domains = qw(
      httpupdate.cpanel.net
      securedownloads.cpanel.net
    );

    foreach my $domain (@domains) {
        DEBUG("Testing $domain...");
        next if ( gethostbyname($domain) );
        ERROR( '!' x 105 . "\n" );
        ERROR("The system cannot resolve the $domain domain. Check the /etc/resolv.conf file. The system has terminated the installation process.\n");
        FATAL( '!' x 105 . "\n" );
    }
    return;
}

sub ensure_pkgs_installed {
    my ($self) = @_;

    my $pid = $self->run_in_background( sub { $self->install_basic_precursor_packages } );

    # While the ensure is running in the background
    # we show the message warning that they need a clean
    # server
    local $SIG{'INT'} = sub {
        kill( 'TERM', $pid );
        WARN("Install terminated by user input");
        exit(0);    ## no critic qw(NoExitsFromSubroutines)
    };

    $self->preflight_warnings;
    local $?;
    waitpid( $pid, 0 );
    if ( $? != 0 ) {
        FATAL("ensure_pkgs_install failed: $?");
    }

    return;
}

sub preflight_warnings {
    my ($self) = @_;
    INFO("");
    INFO("cPanel Layer 1 Installer Starting...");
    INFO("");
    INFO("Warning  !!!  Warning  !!!  WARNING  !!!  Warning  !!!  Warning");
    INFO("---------------------------------------------------------------");
    INFO("");
    INFO("This installer will overwrite all of your configuration files.");
    INFO("");
    INFO("    If this is not a fresh/clean server, hit Ctrl+C NOW to");
    INFO("    avoid losing data.");
    INFO("");
    INFO("");

    # WP2 install is the only place that's currently using this reboot
    # logic, so let's skip the scary warning for everyone else
    if ( $self->is_wp2 && !-e NO_REBOOT_TOUCH_FILE ) {
        INFO("This installer will reboot the system under certain conditions.");
        INFO("");
        INFO("    To prevent an automatic reboot, place a touch file at:");
        INFO( "        " . NO_REBOOT_TOUCH_FILE );
        INFO("    before the installer completes, or restart the installer");
        INFO("    with the --no-reboot argument.");
        INFO("");
        INFO("");
    }
    INFO("---------------------------------------------------------------");
    INFO("Warning  !!!  Warning  !!!  WARNING  !!!  Warning  !!!  Warning");
    INFO("");
    INFO("Waiting 10 seconds");
    $self->_pause_for_ten_seconds();

    return;
}

sub _pause_for_ten_seconds {
    for ( 1 .. 10 ) { print '.'; sleep(1); }
    print "\n";
    return;
}

sub run_in_background {
    my ( $self, $sub ) = @_;

  FORK: {
        my $pid = fork;
        return $pid if $pid;    # Parent.

        if ( !defined $pid ) {  # Still the parent but fork didn't work!
            if ( $! == EAGAIN ) {

                # EAGAIN is the supposedly recoverable fork error
                WARN("Fork failed! Trying again.");
                sleep 5;
                redo FORK;
            }
            else {
                # weird fork error
                FATAL("Can't fork: $!\n");
            }
        }
    }

    begin_collect_output();

    local $@;
    eval { $sub->(); };

    emit_collected_output();
    die if $@;

    exit(0);
}

sub update_system_clock {
    my ($self) = @_;

    my @date_cmd;
    my $binary_name;
    if ( $self->distro_type eq 'rhel' && $self->distro_major >= 8 ) {    # only rhel 8 doesn't provide rdate!
        $binary_name = 'chronyc';
        @date_cmd    = -x '/bin/chronyc' ? ( '/bin/chronyc', 'makestep' ) : ();
    }
    else {
        $binary_name = 'rdate';
        foreach my $bin (qw { /usr/sbin/rdate /usr/bin/rdate /bin/rdate }) {
            next unless -x $bin;
            @date_cmd = ( $bin, '-s', 'rdate.cpanel.net' );
            last;
        }
    }

    # Complain if we don't have an rdate binary.
    if ( !@date_cmd ) {
        ERROR("The system could not set the system clock because the $binary_name binary is missing.");
        return;
    }

    # Set the clock
    my $was = time();
    Common::ssystem(@date_cmd);
    my $now = time();
    INFO( "The system set the clock to: " . localtime($now) );
    my $change = $now - $was;

    # Adjust the start time if it shifted more than 10 seconds.
    if ( abs($change) > 10 ) {
        WARN("The system changed the clock by $change seconds.");
        $self->{'install_start'} = $self->install_start + $change;
        WARN( "The system adjusted the starting time to " . localtime( $self->install_start ) . "." );
    }
    else {
        INFO("The system changed the clock by $change seconds.");
    }

    return 1;
}

sub do_initial_clock_update {
    my ($self) = @_;

    # Sync the clock.
    if ( !$self->update_system_clock ) {
        WARN( "The current system time is set to: " . `date` );    ## no critic(ProhibitQxAndBackticks)
        WARN("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
        WARN("The installation process could not verify the system time. The utility to set time from a remote host, rdate or chrony, is not installed.");
        WARN("If your system time is incorrect by more than a few hours, source compilations will subtly fail.");
        WARN("This issue may result in an overall installation failure.");
        WARN("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
    }
    return;
}

# Start nscd if its not running since it will improve rpm install time
sub start_nscd {
    my ($self) = @_;

    return Common::ssystem("ps -U nscd -h 2>/dev/null || /sbin/service nscd start");
}

sub DESTROY {
    my ($self) = @_;

    return if $INC{'Test/More.pm'};             # Required for testing.
    return unless $self;
    return unless $self->{'original_pid'};
    $self->{'original_pid'} == $$ or return;    # this is a child process so no need to cleanup the lock.
    unlink LOCK_FILE;

    CpanelGPG::cleanup_gpg_homedir();
    return;
}

sub remove_distro_software { die };    # Needs to be implemented in child classes.

# This code is somewhat of a duplication of the code for updatenow that blocks updates based on configuration
# settings. It needs to be here also because of the bootstrap level nature for when this needs to run.
sub check_for_install_version_blockers {
    my ($self) = @_;

    my $lts_version  = $self->lts_version();
    my $tier         = CpanelConfig::tier();
    my $product_name = $self->product_name();

    if ( $self->is_wp2 ) {

        if ( $self->distro_major != 8 ) {

            # do not check for CloudLinux yet:
            #   - we are going to attempt to deploy CloudLinux when possible
            #   - the build itself aborts on unsupported distro
            FATAL("$product_name only supports CloudLinux 8 distribution.");
        }

        # we could also check for build.json
        # Also, we can't use the 'a' modifier on regexp here until CloudLinux 6
        # is not a supported install target. Please add it back after that is
        # no longer a concern.
        my $is_wp2_version = $self->cpanel_version =~ m<^(11\.)?\d+[13579]\.8\d{3}>
          || $self->cpanel_version =~ m{^(11\.)?\d+[02468]\.1(?:\.|$)};

        if ( !$is_wp2_version ) {
            FATAL( 'Unusupported version "' . $self->cpanel_version . qq[" for $product_name.] );
        }

    }

    my $should_block_msg = $self->_should_block_on_this_version_and_os();
    FATAL($should_block_msg) if $should_block_msg;

    my $staging_dir = CpanelConfig::x_from_config('STAGING_DIR');
    if ( $staging_dir ne '' && $staging_dir ne '/usr/local/cpanel' ) {
        FATAL("STAGING_DIR must be set to /usr/local/cpanel during installs.");
    }

    # pull in cpanel.config settings or return if the file's not there (defaults will assert)
    my $cpanel_config = CpanelConfig::read_config('/var/cpanel/cpanel.config');

    # This is distro specific.
    $self->verify_mysql_version($cpanel_config);

    if ( defined $cpanel_config->{'mailserver'} && $cpanel_config->{'mailserver'} !~ m/^(dovecot|disabled)$/i ) {
        FATAL("You must use 'dovecot' or 'disabled' for the mailserver in the /var/cpanel/cpanel.config file for cPanel & WHM version $lts_version.");
    }

    if ( defined $cpanel_config->{'local_nameserver_type'} && $cpanel_config->{'local_nameserver_type'} !~ m/^(powerdns|bind|disabled)$/i ) {
        FATAL("You must use 'powerdns', 'bind' or 'disabled' for the local_nameserver_type in the /var/cpanel/cpanel.config file. For more information, see: https://docs.cpanel.net/whm/service-configuration/nameserver-selection/");
    }

    return;

}

sub _should_block_on_this_version_and_os {
    my ($self) = @_;
    my ( $lts_version, $product_name ) = ( $self->lts_version(), $self->product_name() );
    my ( $distro, $major, $minor ) = @{ $self->{'os'} }{qw{distro major minor}};
    my %errors = (
        'no_debian'     => "Ubuntu is the only debian distro currently supported by $product_name",
        'above_maximum' => "$product_name does not support $distro $major.$minor on version $lts_version. For more information, read about our supported versions at https://go.cpanel.net/system-requirements",
        'below_minimum' => "You cannot install versions of $product_name prior to $product_name version " . MINIMUM_CPANEL_VERSION_SUPPORTED . '.',
    );
    return $errors{'no_debian'} if $self->distro_type eq 'debian' && $self->distro_name ne "ubuntu";
    my $max_version = OS_MAXIMUM_VERSIONS->{"${distro}${major}"};
    return $errors{'above_maximum'} if $max_version                                    && $lts_version > $max_version;
    return $errors{'below_minimum'} if $lts_version < MINIMUM_CPANEL_VERSION_SUPPORTED && !$self->{'options'}{'force'};
    return;
}

sub bootstrap_cpanel_perl {
    my ($self) = @_;

    # Make sure the cPanel key is in place.
    CpanelGPG::fetch_gpg_key_once();

    my $cpanel_version = $self->cpanel_version;

    # Install cPanel files.
    INFO("Installing bootstrap cPanel Perl");

    # Download the tar.gz files and extract them instead.
    my $script = 'fix-cpanel-perl';
    my $source = "/cpanelsync/$cpanel_version/cpanel/scripts/$script.xz";

    unlink $script;

    DEBUG("Retrieving the $script file from $source if available...");

    # download file in current directory (inside the self extracted tarball)
    Common::cpfetch($source);

    chmod 0700, $script;

    INFO("Running script $script to bootstrap cPanel Perl.");

    my $exit;

    # Retry a few times if one of the http request failed
    my $max = 3;
    foreach my $iter ( 1 .. $max ) {
        $exit = Common::ssystem("./$script");
        if ( $exit == 0 ) {
            INFO("Successfully installed cPanel Perl minimal version.");
            return;
        }

        WARN("Run #$iter/$max failed to run script $script");
        last if $iter == $max;
        sleep 5;
    }

    my $signal = $? % 256;

    # This isn't going to return actually. It's going to die.
    ERROR("Failed to run script $script to bootstrap cPanel Perl.");
    return FATAL("The script $script terminated with the following exit code: $exit ($signal); The cPanel & WHM installation process cannot proceed.");
}

sub pre_checks_while_waiting_for_fix_cpanel_perl {
    my ($self) = @_;

    # Make sure the OS is relatively clean.
    $self->check_no_mysql();

    # Check that we're in runlevel 3.
    $self->check_for_multiuser;

    # Assure dnsonly/standard matches chosen installer.
    $self->check_license_conflict();

    # Convert to CloudLinux for WP2
    $self->deploy_cloudlinux() if $self->is_wp2();

    # TODO: Get rid of these files and replace them with /var/cpanel/dnsonly
    # Disable services by touching files.
    if ( $self->is_dnsonly() ) {
        my @dnsonlydisable = qw( cpdavd );
        foreach my $dis_service (@dnsonlydisable) {
            $self->touch( '/etc/' . $dis_service . 'disable' );
        }
    }

    create_slash_scripts_symlink();

    # Some checks may be done in the child class.
    return;
}

sub create_slash_scripts_symlink {
    if ( -e '/scripts' && !-l '/scripts' ) {
        if ( !-d '/scripts' ) {
            WARN("The system detected /scripts as a file. Moving it to a new location...");
            Common::ssystem( qw{/bin/mv /scripts}, "/scripts.o.$$" );
        }
        else {
            WARN("The system detected the /scripts directory. Moving its contents to the /usr/local/cpanel/scripts directory...");
            Common::ssystem(qw{mkdir -p /usr/local/cpanel/scripts});
            Common::ssystem('cd / && tar -cf - scripts | (cd /usr/local/cpanel && tar -xvf -)');
            Common::ssystem(qw{/bin/rm -rf /scripts});
        }
    }
    unlink qw{/scripts};

    # This symlink *must* be relative in order to allow future in-place OS upgrades.
    symlink(qw{usr/local/cpanel/scripts /scripts}) unless -e '/scripts';

    if ( !-l '/scripts' ) {
        WARN("The /scripts directory must be a symlink to the /usr/local/cpanel/scripts directory. cPanel & WHM does not use the /scripts directory.");
    }
    else {
        DEBUG('/scripts symlink is set to point to /usr/local/cpanel/scripts');
    }

    return;
}

sub check_no_mysql {

    # This can cause failures if the database is newer than the version we're
    # going to install.
    INFO('Checking for an existing MySQL or MariaDB instance...');

    my $mysql_dir = '/var/lib/mysql';
    return unless -d $mysql_dir;
    my $nitems = 0;
    if ( opendir( my $dh, $mysql_dir ) ) {
        $nitems = scalar grep { !/\A(?:\.{1,2}|lost\+found)\z/ } readdir $dh;
        closedir($dh);
    }
    return unless $nitems;

    ERROR("The installation process found evidence that MySQL or MariaDB was installed on this server:");
    ERROR("The $mysql_dir directory is present and not completely empty.");
    FATAL('You must install cPanel & WHM on a clean server.');
    return;
}

sub check_for_multiuser {
    my ($self) = @_;

    # If we can detect multiuser, then we're good. Do no more checks.
    return if $self->check_systemd_multiuser_target;
    return $self->check_runlevel;    # Will fail if it doesn't succeed.
}

# This code is probably dead for all systemd systems. It's not clear when a system would be valid with multi-user being inactive but runlevel being 3.
# This code can probably be removed when we drop RHEL 6 support.
sub check_runlevel {
    my ($self) = @_;

    # From `man runlevel` :
    #       Table 1. Mapping between runlevels and systemd targets
    #       ┌─────────┬───────────────────┐
    #       │Runlevel │ Target            │
    #       ├─────────┼───────────────────┤
    #       │0        │ poweroff.target   │
    #       ├─────────┼───────────────────┤
    #       │1        │ rescue.target     │
    #       ├─────────┼───────────────────┤
    #       │2, 3, 4  │ multi-user.target │
    #       ├─────────┼───────────────────┤
    #       │5        │ graphical.target  │
    #       ├─────────┼───────────────────┤
    #       │6        │ reboot.target     │
    #       └─────────┴───────────────────┘

    my $runlevel = `runlevel`;    ## no critic(ProhibitQxAndBackticks)
    chomp $runlevel;
    my ( $prev, $curr ) = split /\s+/, $runlevel;

    my $message;

    # currently we allow runlevel 3 or 5, as 5 is the default even on Ubuntu Server, just with no X installed or running
    # runlevel can also return unknown
    if    ( !defined $curr )           { $message = "The installation process could not determine the server's current runlevel."; }
    elsif ( $curr != 3 && $curr != 5 ) { $message = "The installation process detected that the server was in runlevel $curr."; }
    else                               { return; }

    # the system claims to be in an unsupported runlevel.
    if ( $self->force ) {
        WARN($message);
        WARN('The server must be in runlevel 3 or 5. Proceeding anyway because --force was specified!');
        return;
    }

    ERROR("The installation process detected that the server was in runlevel $curr.");
    FATAL('The server must be in runlevel 3 or 5 before the installation can continue.');

    return die "unreachable code";
}

sub check_systemd_multiuser_target {
    my ($self) = @_;

    return if $self->distro_major == 6;    # Cloudlinux 6 is the only thing we support that's not systemd.

    local $?;
    `systemctl is-active multi-user.target >/dev/null 2>&1`;    ## no critic(ProhibitQxAndBackticks)
    return 1 if $? == 0;                                        # We're in multiuser state.
    if ( $self->force ) {
        WARN('The installation process detected that the multi-user.target is not active (boot is probably not finished).');
        WARN('The multi-user.target must be active. Proceeding anyway because --force was specified!');
    }
    else {
        ERROR('The installation process detected that the multi-user.target is not active (boot is probably not finished).');
        FATAL('The multi-user.target must be active before the installation can continue.');
    }

    return;
}

sub verify_url {
    my ( $self, $ip ) = @_;
    $ip ||= '';

    return qq[https://verify.cpanel.net/xml/verifyfeed?ip=$ip];
}

#
#   block cPanel&WHM install when a DNSONLY license is valid for the server
#   block DNSONLY license when a cPanel license is valid for the server
#
sub check_license_conflict {
    my ($self) = @_;

    return if $self->skip_license_check;

    my $ip = guess_ip();

    # skip check and continue install if we cannot guess up
    return unless defined $ip;

    INFO("Checking for existing active license linked to IP '$ip'.");

    my $verify_license_xml = q[verify.license.xml];

    my $url = $self->verify_url($ip);

    # check verify.cpanel.net - the xml one...
    Common::fetch_url_to_file( $url, $verify_license_xml );

    my $active_basepkg = 0;
    my $package        = "";

    {
        open( my $fh, '<', $verify_license_xml ) or FATAL("Cannot read file $verify_license_xml.");
        while ( my $line = <$fh> ) {

            next unless $line =~ m/status="1"/;     # package is active
            next unless $line =~ m/basepkg="1"/;    # package is a base package (skipping packages like kernelcare, cloudlinux & co)

            if ( $line =~ m/producttype="([0-9]+)"/ ) {
                $active_basepkg = $1;
                $line =~ m/package="([^"]+)"/;
                $package = $1;

                last;
            }
        }
    }

    if ( $self->is_wp2 ) {

        my $is_internal_network = $ip =~ m{^10\.};    # Note: ip is coming from MYIP

        if ( !$is_internal_network ) {                # skip the license check behind internal network

            # check for the WP2 license
            if ( $active_basepkg != PRODUCT_WP2 ) {
                ERROR("Unexpected license type found for your IP: https://verify.cpanel.net/app/verify?ip=$ip");
                FATAL("Installation aborted. Cannot find a valid WP Squared license for your IP: $ip.");
            }
        }

        return;
    }

    return unless $active_basepkg;

    if ( $self->is_dnsonly ) {

        # we cannot install dnsonly if a cPanel license exists
        if ( $active_basepkg != PRODUCT_DNSONLY ) {
            unlink '/var/cpanel/dnsonly';
            ERROR("Unexpected license type found for your IP: https://verify.cpanel.net/app/verify?ip=$ip");
            ERROR("Current active package is $package");
            FATAL("Installation aborted. Perhaps you meant to install latest instead of latest-dnsonly? If not please cancel your cPanel license before installing a cPanel DNSONLY server.");
        }
    }
    else {
        # we cannot install cPanel if a dnsonly license exists
        if ( $active_basepkg & PRODUCT_DNSONLY ) {
            ERROR("Unexpected license type found for your IP: https://verify.cpanel.net/app/verify?ip=$ip");
            FATAL("Installation aborted. Perhaps you meant to install latest-dnsonly instead of latest? If not please cancel your DNSONLY license before installing a cPanel & WHM server.");
        }
    }

    # everything is fine at this point
    return;
}

sub is_cloudlinux {
    my ($self) = @_;

    return $self->distro_name eq 'cloudlinux';
}

sub deploy_cloudlinux {
    my ($self) = @_;

    return if $self->is_cloudlinux();

    my $url = q[https://repo.cloudlinux.com/cloudlinux/sources/cln/cldeploy];

    my $previous_major = $self->distro_major;

    INFO("-------------------------------------------------------");
    INFO("Converting distribution to CloudLinux $previous_major");
    INFO("-------------------------------------------------------");

    my $cldeploy_tmp = q[cldeploy.tmp];
    Common::fetch_url_to_file( $url, $cldeploy_tmp );

    FATAL("Fail to fetch CloudLinux installer from $url") unless -s $cldeploy_tmp;

    my $exit = Common::ssystem( '/usr/bin/bash', $cldeploy_tmp, '-i' );
    unlink($cldeploy_tmp);    # not really necessary: we are inside the temporary self extracted tarball

    INFO("-------------------------------------------------------");
    INFO(".");

    # clear & detect again (on failures we can also switch to a different distro)
    OSDetect::clear();
    $self->detect_distro;

    if ( $exit == 0 ) {

        if ( $self->distro_name ne 'cloudlinux' || $self->distro_major != $previous_major ) {
            FATAL("Fail to convert to cloudlinux $previous_major");
        }

        INFO("-------------------------------------------------------");
        INFO("Successfully converted distribution to CloudLinux $previous_major");
        INFO("-------------------------------------------------------");

        return;
    }

    my $error_msg = "Fail to convert distribution to CloudLinux $previous_major.";

    if ( $self->skip_license_check ) {
        WARN($error_msg);
        WARN("Ignoring License Check as --skip-license-check flag was provided.");
        return;
    }

    FATAL($error_msg);

    return;
}

sub guess_ip {
    my $url = CpanelConfig::myip_url();
    DEBUG("Using MyIp URL to detect server IP '$url'.");
    my $file = q[guess.my.ip];

    my $max = 3;
    foreach my $iter ( 1 .. $max ) {
        unlink $file;
        my ( $ok, $msg ) = Common::fetch_url_to_file( $url, $file );
        last if $ok;
        if ( $iter == $max ) {
            FATAL("Failed to call URL $url to detect your IP.");
        }
        WARN("Call to $url fails, giving it another try [$iter/$max]");
        sleep 3;
    }

    my $ip;

    {
        open( my $fh, '<', $file ) or FATAL("Cannot read file $file.");
        $ip = readline($fh);
        close($fh);
    }

    chomp($ip) if defined $ip;

    if ( !length $ip ) {

        # could also use FATAL - be relax for now to avoid false positives
        WARN("Fail to guess your IP using URL $url.");
        return;
    }

    # sanitize the IP - Ipv4 or Ipv6 character set only
    if ( $ip !~ qr{^[0-9a-f\.:]+$}i ) {

        # could also use FATAL - be relax for now to avoid false positives
        WARN("Invalid IP address '$ip' returned by $url");
        return;
    }

    return $ip;
}

sub background_download_packages_used_during_initial_install { die 'unimplemented' }

# NOTE, this is as "concise" as I could make this, though I'm
# basically copying what Cpanel::FileUtils::TouchFile does.
my @touch_meths = (
    sub { return 1 if utime( undef, undef, $_[0] ) },
    sub { return !Common::ssystem( '/bin/touch', $_[0] ) },
);

sub touch {
    my ( $self, $file ) = @_;

    foreach (@touch_meths) {
        return if $_->($file);
    }

    die "Can't touch file $file";
}

sub updatenow {
    my ($self) = @_;

    INFO("Downloading updatenow.static");

    # Download the tar.gz files and extract them instead.

    my $install_version = $self->cpanel_version();
    my $source          = "/cpanelsync/$install_version/cpanel/scripts/updatenow.static.bz2";

    DEBUG("Retrieving the updatenow.static file from $source...");

    # download file in current directory (inside the self extracted tarball)
    unlink 'updatenow.static';
    Common::cpfetch($source);
    chmod 0755, 'updatenow.static';
    my $exit;

    my @flags;
    push @flags, '--skipapache'    => $self->skip_apache;
    push @flags, '--skipreposetup' => $self->skip_repo_setup;

    for ( 1 .. 5 ) {    # Re-try updatenow if it fails.
        INFO("Closing the installation log and passing output control to the updatenow.static file...");

        # close the log file so it can be re-opened by updatenow.
        CpanelLogger::close_log_file();

        my $log_file = CpanelLogger::LOG_FILE();
        $exit = system( './updatenow.static', '--upcp', '--force', "--log=$log_file", @flags );

        # Re-open file regardless of updatenow success.
        CpanelLogger::open_log_for_append();

        return if ( !$exit );

        DEBUG("The installation process detected a failed synchronization. The system will reattempt the synchronization with the updatenow.static file...");
    }

    my $signal = $exit % 256;
    $exit = $exit >> 8;

    FATAL( "The installation process was unable to synchronize cPanel & WHM. Verify that your network can connect to " . CpanelConfig::source() . " and rerun the installer." );
    FATAL("The updatenow.static process terminated with the following exit code: $exit ($signal); The cPanel & WHM installation process cannot proceed.");
    return;
}

sub assure_nobody {

    my $systemd_nobody_file = '/etc/systemd/dont-synthesize-nobody';
    if ( -d "/etc/systemd" && !-e $systemd_nobody_file ) {
        if ( open( my $fh, '>', $systemd_nobody_file ) ) {
            print $fh '';
            close $fh;
        }
        INFO("Touch $systemd_nobody_file");
    }

    my $uid = getpwnam("nobody");
    my $gid = getgrnam("nobody");
    my @ids = ( 65534, 99 );

    my $user_needs_informed = 0;

    if ( !defined $gid ) {
        $user_needs_informed = 1;

        my $addgroup = -x '/usr/sbin/groupadd' ? "groupadd" : "addgroup";
        for my $id (@ids) {
            Common::ssystem( $addgroup, '--system', '--gid', $id, 'nobody', { 'ignore_errors' => 1 } );
            $gid = getgrnam("nobody");
            last if defined $gid;
        }

        if ( !defined $gid ) {
            Common::ssystem( $addgroup, qw/--system nobody/, { 'ignore_errors' => 1 } );
        }

        $gid = getgrnam("nobody");
        FATAL("Could not ensure `nobody` group") if !defined $gid;
    }

    if ( !defined $uid ) {
        $user_needs_informed = 1;

        my $flags = -x '/usr/sbin/groupadd' ? "" : "--disabled-password --disabled-login";
        for my $id (@ids) {
            Common::ssystem( "adduser --system --uid $id --gid $gid --home / --no-create-home --shell /sbin/nologin $flags nobody", { 'ignore_errors' => 1 } );
            $uid = getpwnam("nobody");
            last if defined $uid;
        }

        if ( !defined $uid ) {
            Common::ssystem( "adduser --system --gid $gid --home / --no-create-home --shell /sbin/nologin $flags nobody", { 'ignore_errors' => 1 } );
        }

        $uid = getpwnam("nobody");
        FATAL("Could not ensure `nobody` user") if !defined $uid;
    }

    # if already done, its a noop. adduser’s --gid does not make this happen
    Common::ssystem( 'usermod', '-g', $gid, 'nobody', { 'ignore_errors' => 1 } );

    my $home = ( getpwnam("nobody") )[7];
    if ( !-d $home ) {
        mkdir $home, 0755;
        chown $uid, $gid, $home;
    }

    if ( !-d $home ) {
        WARN('Detected non-existent home directory for `nobody`.');
        WARN('');
        WARN('This situation can result in some harmless STDERR going to your web server’s error log as errors.');
        WARN('');
        WARN('If you experience this your options are:');
        WARN('');
        WARN('    1. Ignore the log entries');
        WARN('    2. Create the directory “$home” if it is safe to do so.');
        WARN('    3. Change the `nobody` user’s home directory to one that exists. e.g. `usermod --home / nobody`');
        WARN('');
    }

    INFO("'nobody' user created with UID $uid and GID $gid") if $user_needs_informed;

    return;
}

1;
