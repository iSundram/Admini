package CpanelMySQL;

#                                      Copyright 2024 WebPros International, LLC
#                                                           All rights reserved.
# copyright@cpanel.net                                         http://cpanel.net
# This code is subject to the cPanel license. Unauthorized copying is prohibited.

use strict;
use warnings;

use Common ();    # read_config
use CpanelLogger;

# Determine whether mysql-version set in config is MariaDB
sub version_is_mariadb {
    my ($version) = @_;

    if ( $version && $version =~ m{^([0-9]{1,2})(?:\.[0-9])?} ) {
        return ( $1 >= 10.0 ? 1 : 0 );
    }

    return 0;
}

sub get_db_identifiers {
    my ($db_version) = @_;

    if ( version_is_mariadb($db_version) ) {
        return ( 'plain' => 'mariadb', 'stylized' => 'MariaDB' );
    }

    return ( 'plain' => 'mysql', 'stylized' => 'MySQL®' );
}

sub get_max_allowed_db_version {
    my ( $cpanel_version, $db_type ) = @_;

    # cpver is the first cpanel version that allows the database version defined by dbver
    # For example, v87 is the first version that allows MySQL 8 to be installed.
    # All cPanel versions lower than 87 will allow up to MySQL 5.7.
    my %max_combos = (
        mysql => [
            {
                cpver => '123',
                dbver => '8.4'
            },
            {
                cpver => '87',
                dbver => '8.0'
            },
            {
                cpver => '1',
                dbver => '5.7',
            },
        ],
        mariadb => [
            {
                cpver => '97',
                dbver => '10.5',
            },
            {
                cpver => '99',
                dbver => '10.6',
            },
            {
                cpver => '1',
                dbver => '10.3',
            },
            {
                cpver => '113',
                dbver => '10.11',
            },
            {
                cpver => '123',
                dbver => '11.4',
            },
        ],
    );

    my ($max) = sort { $b->{'cpver'} <=> $a->{'cpver'} } grep { my $ver = $_; $cpanel_version >= $ver->{cpver} } @{ $max_combos{$db_type} };
    return $max->{'dbver'};

}

sub get_min_allowed_db_version {
    my ( $cpversion, $osver, $type ) = @_;

    # If OS Version is 8 or higher, enforce MySQL 8/Maria 10.5 as default.
    # Otherwise, use 5.5, 5.6 or 5.7 as is outlined in our version matrix.
    my %at_least_req_combos = (
        'mariadb' => [
            {
                'osver' => 7,
                'cpver' => 117,
                'dbver' => '10.5',
            },
            {
                'osver' => 8,
                'cpver' => 117,
                'dbver' => '10.5',
            },
            {
                'osver' => 6,
                'cpver' => 48,
                'dbver' => '10.3',
            },
            {
                'osver' => 9,
                'cpver' => 97,
                'dbver' => '10.5',
            },
            {
                'osver' => 20,
                'cpver' => 117,
                'dbver' => '10.5',
            },
            {
                'osver' => 22,
                'cpver' => 117,
                'dbver' => '10.6',
            },
        ],
        'mysql' => [
            {
                'osver' => 7,
                'cpver' => 117,
                'dbver' => '8.0',
            },
            {
                'osver' => 8,
                'cpver' => 91,
                'dbver' => '8.0',
            },
            {
                'osver' => 6,
                'cpver' => 80,
                'dbver' => '5.6',
            },
            {
                'osver' => 6,
                'cpver' => 48,
                'dbver' => '5.5',
            },
            {
                'osver' => 20,
                'cpver' => 117,
                'dbver' => '8.0',
            },
        ],
    );

    # Choose the possible combo with the cpversion "closest" to passed in.
    # Sorting the grep result will enable this rather straightforwardly.
    my ($winning_combo) = sort { $b->{'cpver'} <=> $a->{'cpver'} or $b->{'osver'} <=> $a->{'osver'} } grep {
        my $hr = $_;
        $osver >= $hr->{'osver'} && $cpversion >= $hr->{'cpver'}
    } @{ $at_least_req_combos{$type} };

    return $winning_combo->{'dbver'};
}

sub get_db_version_advice {
    my ( $db_version, $cpanel_version, $os_version, $db_type ) = @_;

    my $max = get_max_allowed_db_version( $cpanel_version, $db_type );
    my $min = get_min_allowed_db_version( $cpanel_version, $os_version, $db_type );

    my $advice;
    my $rec_version;

    if ( _compare_versions( $db_version, $max ) > 0 ) {
        $advice      = 'lower';
        $rec_version = $max;
    }
    elsif ( _compare_versions( $db_version, $min ) < 0 ) {
        $advice      = 'higher';
        $rec_version = $min;
    }

    return { action => $advice, ver => $rec_version };
}

# returns 1 if $v1 is greater than $v2
# returns -1 if $v1 is less than $v2
# returns 0 if $v1 and $v2 are equal
sub _compare_versions {
    my ( $v1,       $v2 )     = @_;
    my ( $v1_major, $v1_min ) = split /\./, $v1;
    my ( $v2_major, $v2_min ) = split /\./, $v2;

    return $v1_major <=> $v2_major || $v1_min <=> $v2_min;
}
1;
