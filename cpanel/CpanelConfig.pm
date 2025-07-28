package CpanelConfig;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.

use strict;
use warnings;

use Common ();       # for cpfetch
use CpanelLogger;    # INFO, etc.

use constant {
    DEFAULT_PRODUCT        => q[standard],
    DEFAULT_SOURCE         => q[httpupdate.cpanel.net],
    DEFAULT_TIER           => q[release],
    DEFAULT_TIERS_FILENAME => q[TIERS],
    DEFAULT_MYIP_URL       => q[https://myip.cpanel.net/v1.0/],
    WP2_TIERS_FILE         => q[wp2-TIERS],
    CPANEL_CONFIG          => q[/var/cpanel/cpanel.config],
    CPSOURCES_CONFIG       => q[/etc/cpsources.conf],
    CPUPDATE_CONFIG        => q[/etc/cpupdate.conf],
};

# Cache everything, gotta go fast
my ( %TIER_CACHE, %CONF_CACHE );

# Accessors (get/set)
my ( $TIER, $SOURCE, $MYIP, $PRODUCT );

sub tier {
    my ($tier) = @_;
    $TIER ||= ( $tier || x_from_config('CPANEL') || DEFAULT_TIER() );

    # Deal with version numbers without 11.
    if ( $TIER =~ /^(?!11\.)\d{1,3}(?:\.\d+)*$/ ) {
        WARN("Version updated from $TIER to 11.$TIER");
        $TIER = '11.' . $TIER;
    }

    if ( defined $TIER && length $TIER ) {
        my $cfg = read_config( CPUPDATE_CONFIG() );
        $cfg->{'CPANEL'} = $TIER;
        write_config( CPUPDATE_CONFIG(), $cfg );
    }

    return $TIER;
}

sub source {
    my ($source) = @_;
    if ( defined $source && length $source ) {
        my $cfg = read_config( CPSOURCES_CONFIG() );
        $cfg->{'HTTPUPDATE'} = $SOURCE = $source;
        write_config( CPSOURCES_CONFIG(), $cfg );
    }
    return $SOURCE ||= ( x_from_config('HTTPUPDATE') || DEFAULT_SOURCE() );
}

# Allow MYIP to be set in case we want it for the future as an opt.
sub myip_url {
    my ($url) = @_;
    if ( length $url ) {
        my $cfg = read_config( CPSOURCES_CONFIG() );
        $cfg->{'MYIP'} = $MYIP = $url;
        write_config( CPSOURCES_CONFIG(), $cfg );
    }
    $MYIP = x_from_config('MYIP') || DEFAULT_MYIP_URL;

    return $MYIP;
}

sub product {
    my ($product) = @_;
    if ( defined $product && length $product ) {
        die "Invalid product: $product" if !grep { $_ eq $product } qw{standard dnsonly wp2};
        return $PRODUCT = $product;
    }
    return $PRODUCT ||= DEFAULT_PRODUCT();
}

# Product type checkers
sub is_wp2 {
    return product() eq 'wp2' ? 1 : 0;
}

sub is_dnsonly {
    return product() eq 'dnsonly' ? 1 : 0;
}

my %x2cfg_map = (
    'signature_validation' => CPANEL_CONFIG(),
    ( map { $_ => CPSOURCES_CONFIG() } qw{HTTPUPDATE MYIP} ),
    ( map { $_ => CPUPDATE_CONFIG() } qw{CPANEL STAGING_DIR} ),
);

# Note: Literally not possible for return to be undef here no matter what
sub x_from_config {
    my ($x) = @_;
    return '' if !length $x || !length $x2cfg_map{$x};

    # Any non-undef entry from read_config here will always be string
    # and not something crazy like number or hash
    return read_config( $x2cfg_map{$x} )->{$x} // '';
}

# Shortcut method because reviewer wanted it to stay
sub signatures_enabled {
    return x_from_config('signature_validation') eq 'On';
}

sub get_lts_version {
    my ($cpanel_version) = @_;

    length $cpanel_version || die;
    my ( undef, $lts_version ) = split( qr/\./, $cpanel_version );

    $lts_version or FATAL("The system could not determine the LTS version for $cpanel_version");

    return $lts_version;
}

sub get_cpanel_version {
    my $tier    = tier();
    my $version = guess_version_from_tier($tier);

    $version or FATAL("The system could not determine the target version from your tier: $tier");
    return $version;
}

sub get_TIERS_filename {

    return WP2_TIERS_FILE if is_wp2();
    return DEFAULT_TIERS_FILENAME;
}

sub clear_conf_cache_for {
    my ($cachekey) = @_;
    undef $SOURCE if length $cachekey && $cachekey eq 'HTTPUPDATE';
    undef $TIER   if length $cachekey && $cachekey eq 'CPANEL';
    undef $MYIP   if length $cachekey && $cachekey eq 'MYIP';
    return $cachekey ? ( delete $CONF_CACHE{ $x2cfg_map{$cachekey} } ) : ( %CONF_CACHE = () );
}

sub read_config {
    my ($file) = @_;
    ( length $file ) or die 'No file passed to read_config';
    return $CONF_CACHE{$file} if ref $CONF_CACHE{$file} eq 'HASH';

    my $config = {};

    open( my $fh, "<", $file ) or return $config;
    while ( my $line = readline $fh ) {
        chomp $line;
        if ( $line =~ m/^\s*([^=]+?)\s*$/ ) {
            my $key = $1 or next;    # Skip loading the key if it's undef or 0
            $config->{$key} = undef;
        }
        elsif ( $line =~ m/^\s*([^=]+?)\s*=\s*(.*?)\s*$/ ) {
            my $key = $1 or next;    # Skip loading the key if it's undef or 0
            $config->{$key} = $2;
        }
    }
    return $CONF_CACHE{$file} = $config;
}

sub write_config {
    my ( $file, $cfg ) = @_;
    ( length $file ) or die 'No file passed to write_config';

    my $out = '';
    $out .= "$_=$cfg->{$_}\n" for keys(%$cfg);
    if ($out) {
        open( my $fh, ">", $file ) or die "Can't open $file for writing: $!";
        print $fh $out;
    }

    # Update cache and return
    return $CONF_CACHE{$file} = $cfg;
}

sub guess_version_from_tier {
    my ($tier2guess) = @_;
    $tier2guess ||= tier();

    if ( defined( $TIER_CACHE{$tier2guess} ) ) {
        return $TIER_CACHE{$tier2guess};
    }

    # Support version numbers as tiers.
    if ( $tier2guess =~ /^\s*[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+\s*$/ ) {
        $TIER_CACHE{$tier2guess} = $tier2guess;
        return $tier2guess;
    }

    my $TIERS_filename = get_TIERS_filename();

    # Download the file.
    Common::cpfetch("/cpanelsync/$TIERS_filename");
    -e $TIERS_filename or FATAL("The installation process could not fetch the /cpanelsync/$TIERS_filename file from the httpupdate server.");

    # Parse the downloaded TIERS data for our tier. (Stolen from Cpanel::Update)
    open( my $fh, '<', $TIERS_filename ) or FATAL("The system could not read the downloaded $TIERS_filename file.");
    while ( my $tier_definition = <$fh> ) {
        chomp $tier_definition;
        next if ( $tier_definition =~ m/^\s*#/ );    # Skip commented lines.
        ## e.g. edge:11.29.0 (requires two dots)
        next if ( $tier_definition !~ m/^\s*([^:\s]+)\s*:\s*(\S+)/ );

        my ( $remote_tier, $remote_version ) = ( $1, $2 );
        $TIER_CACHE{$remote_tier} = $remote_version;
    }
    close $fh;

    # Set any disabled tiers to install-fallback if possible.
    foreach my $key ( keys %TIER_CACHE ) {
        next if $key eq 'install-fallback';
        if ( $TIER_CACHE{$key} && $TIER_CACHE{'install-fallback'} && $TIER_CACHE{$key} eq 'disabled' ) {
            $TIER_CACHE{$key} = $TIER_CACHE{'install-fallback'};
        }
    }

    # Fail if the tier is not present.
    if ( !$TIER_CACHE{$tier2guess} ) {
        FATAL("The specified tier ('$tier2guess') in the /etc/cpupdate.conf file is not a valid cPanel & WHM tier.");
    }

    # Fail if the tier is still disabled.
    if ( $TIER_CACHE{$tier2guess} eq 'disabled' ) {
        FATAL("cPanel has temporarily disabled updates on the central httpupdate servers. Please try again later.");
    }

    return $TIER_CACHE{$tier2guess};
}

1;
