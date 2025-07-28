package CpanelGPG;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.

use strict;
use warnings;

use Common       ();    # fetch_url_to_file
use CpanelConfig ();    # read_config
use CpanelLogger;

use File::Path ();

use constant GPG_HOMEDIR => '/var/cpanel/.gpgtmpdir';
use constant PUBLIC_KEYS => {
    'release'     => 'cPanelPublicKey.asc',
    'development' => 'cPanelDevelopmentKey.asc',
};

use constant GPG_KEYRINGS => {
    'release'     => ['release'],
    'development' => [ 'release', 'development' ],
};

use constant SECURE_DOWNLOADS => 'https://securedownloads.cpanel.net/';

sub _create_gpg_homedir {
    mkdir( GPG_HOMEDIR, 0700 ) if !-e GPG_HOMEDIR;
    return;
}

my $gpg_bin;

sub gpg_bin {

    return $gpg_bin if $gpg_bin;

    for my $bin (qw(/usr/bin/gpg /bin/gpg /usr/local/bin/gpg)) {
        next if ( !-e $bin );
        next if ( !-x _ );
        next if ( -z _ );
        return $gpg_bin = $bin;
    }

    FATAL("The installation process could not find the gpg binary. Install it to a standard location.");
    return;
}

sub cleanup_gpg_homedir {
    return unless -d GPG_HOMEDIR;

    File::Path::remove_tree( GPG_HOMEDIR, { safe => 1 } );

    return;
}

sub keys_to_download {

    my $config   = CpanelConfig::read_config('/var/cpanel/cpanel.config');
    my $keyrings = GPG_KEYRINGS;

    if ( !defined $config->{'signature_validation'} ) {
        my $mirror = CpanelConfig::source();

        if ( $mirror =~ /^(?:.*\.dev|qa-build|next)\.cpanel\.net$/ ) {
            return $keyrings->{'development'};
        }
        else {
            return $keyrings->{'release'};
        }
    }
    elsif ( $config->{'signature_validation'} =~ /^Release and (?:Development|Test) Keyrings$/ ) {
        return $keyrings->{'development'};
    }
    else {
        return $keyrings->{'release'};
    }
}

my $gpg_is_setup;

sub fetch_gpg_key_once {

    return if $gpg_is_setup;

    my $pub_keys = PUBLIC_KEYS;
    _create_gpg_homedir();

    foreach my $key ( sort @{ keys_to_download() } ) {
        INFO("Downloading GPG public key, $pub_keys->{$key}");
        my $target = SECURE_DOWNLOADS . $pub_keys->{$key};
        my $dest   = GPG_HOMEDIR . "/" . $pub_keys->{$key};

        Common::fetch_url_to_file( $target, $dest );
        if ( !-e $dest ) {
            FATAL("Could not download GPG public key at '$target'.");
            return;
        }
        INFO("Importing downloaded GPG public key from '$dest'.");
        my $gpg_cmd = gpg_bin() . " -q --homedir " . GPG_HOMEDIR . " --import " . $dest;
        my $output  = `$gpg_cmd 2>&1`;                                                     ## no critic(ProhibitQxAndBackticks)
        if ( $? != 0 ) {
            WARN("Failed to import GPG public key from '$dest': $output");
        }
    }

    $ENV{'CPANEL_BASE_INSTALL_GPG_KEYS_IMPORTED'} = 1;                                     # in v82+ fix-cpanel-perl will skip gpg keyimport if set
    $gpg_is_setup = 1;

    return;
}

sub verify_file {
    my ( $file, $sig, $url ) = @_;

    fetch_gpg_key_once();

    my @gpg_args = (
        '--logger-fd', '1',
        '--status-fd', '1',
        '--homedir',   GPG_HOMEDIR,
        '--verify',    $sig,
        $file,
    );

    # Verify the validity of the GPG signature.
    # Information on these return values can be found in 'doc/DETAILS' in the GnuPG source.

    my ( %notes, $curnote );
    my ( $gpg_out, $success, $error_status );
    my $gpg_pid = IPC::Open3::open3( undef, $gpg_out, undef, gpg_bin(), @gpg_args );

    while ( my $line = readline($gpg_out) ) {
        if ( $line =~ /^\[GNUPG:\] VALIDSIG ([A-F0-9]+) ([0-9]+-[0-9]+-[0-9]+) ([0-9]+) ([A-F0-9]+) ([A-F0-9]+) ([A-F0-9]+) ([A-F0-9]+) ([A-F0-9]+) ([A-F0-9]+) ([A-F0-9]+)$/ ) {
            $success = 1;
        }
        elsif ( $line =~ /^\[GNUPG:\] NOTATION_NAME (.+)$/ ) {
            $curnote         = $1;
            $notes{$curnote} = '';
            $error_status    = 'Probably invalid notation name';
        }
        elsif ( $line =~ /^\[GNUPG:\] NOTATION_DATA (.+)$/ ) {
            $notes{$curnote} .= $1;
            $error_status = 'Probably invalid notation data';
        }
        elsif ( $line =~ /^\[GNUPG:\] BADSIG ([A-F0-9]+) (.+)$/ ) {
            $error_status = "Invalid signature for $file";
        }
        elsif ( $line =~ /^\[GNUPG:\] NO_PUBKEY ([A-F0-9]+)$/ ) {
            $error_status = "Could not find public key ($1) in keychain";
        }
        elsif ( $line =~ /^\[GNUPG:\] NODATA ([A-F0-9]+)$/ ) {
            $error_status = "Could not find a GnuPG signature in the signature file";
        }
    }

    waitpid( $gpg_pid, 0 );

    $error_status ||= "Unknown error from gpg";

    my $finalize_output = sub { return "$_[0] (file:$file, sig:$sig)" };

    if ($success) {
        INFO( $finalize_output->("Valid signature for $file") );
    }
    else {
        FATAL( $finalize_output->($error_status) );
    }

    # At this point, the signature should be valid.
    # We now need to check to see if the filename signature notation is correct.

    $url =~ s/\.bz2$//;

    if ( defined( $notes{'filename@gpg.notations.cpanel.net'} ) ) {
        my $file_note = $notes{'filename@gpg.notations.cpanel.net'};
        if ( $file_note ne $url ) {
            FATAL( $finalize_output->("Filename notation ($file_note) does not match URL ($url)") );
        }
    }
    else {
        FATAL( $finalize_output->("Signature does not contain the expected filename notation") );
    }

    return;
}

1;
