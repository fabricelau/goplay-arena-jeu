<?php
/**
 * Plugin Name: GoPlay Arena – Jeu Arena Defender
 * Description: Mini-jeux pour les tables (QR code) : Arena Defender, Arena Pilot, Pixel Rush, Laser Reflex, Target Chain, Memory Grid, Color Switch, avec classements du jour et de tous les temps.
 * Version: 1.4.0
 * Author: GoPlay Arena
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'GPAJ_VER', '1.4.0' );

/* jeu => fichier */
function gpaj_base_games() { return array( 'arena' => 'game.html', 'pilot' => 'pilot.html', 'pixel' => 'pixel.html', 'laser' => 'laser.html', 'chain' => 'chain.html', 'memory' => 'memory.html', 'color' => 'color.html' ); }
function gpaj_extra() { $x = get_option( 'gpaj_extra_games', array() ); return is_array( $x ) ? $x : array(); }
function gpaj_games() { $g = gpaj_base_games(); foreach ( gpaj_extra() as $k => $v ) { if ( empty( $g[ $k ] ) ) { $g[ $k ] = $v['file']; } } return $g; }
function gpaj_names() { $x = array(); foreach ( gpaj_extra() as $k => $v ) { $x[ $k ] = $v['name']; } return array_merge( $x, array( 'arena' => 'Arena Defender', 'pilot' => 'Arena Pilot', 'pixel' => 'Pixel Rush', 'laser' => 'Laser Reflex', 'chain' => 'Target Chain', 'memory' => 'Memory Grid', 'color' => 'Color Switch' ) ); }
function gpaj_paths() { $x = array(); foreach ( gpaj_extra() as $k => $v ) { $x[ $k ] = '/jeu/' . $k . '/'; } return array_merge( $x, array( 'arena' => '/jeu/', 'pilot' => '/jeu/pilot/', 'pixel' => '/jeu/pixel/', 'laser' => '/jeu/laser/', 'chain' => '/jeu/chain/', 'memory' => '/jeu/memory/', 'color' => '/jeu/color/' ) ); }
function gpaj_game( $g ) { $g = sanitize_key( (string) $g ); return isset( gpaj_games()[ $g ] ) ? $g : 'arena'; }
function gpaj_sfx( $game ) { return $game === 'arena' ? '' : '_' . $game; }
function gpaj_key_all( $game = 'arena' ) { return 'gpa_jeu_all' . gpaj_sfx( $game ); }
define( 'GPAJ_KEEP', 100 );

function gpaj_tz() { return new DateTimeZone( 'Indian/Reunion' ); }
function gpaj_today() { return ( new DateTime( 'now', gpaj_tz() ) )->format( 'Y-m-d' ); }
function gpaj_key_day( $game = 'arena', $d = null ) { return 'gpa_jeu_day_' . ( $d ? $d : gpaj_today() ) . gpaj_sfx( $game ); }

/* ---------- Page /jeu/ ---------- */
add_action( 'template_redirect', function () {
	$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	$map = array( 'jeu' => 'arena' );
	foreach ( array_keys( gpaj_games() ) as $k ) { $map[ 'jeu/' . $k ] = $k; }
	if ( ! isset( $map[ $path ] ) ) { return; }
	$file = gpaj_games()[ $map[ $path ] ];
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">'
		. '<style>:root{color-scheme:dark;padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}body{margin:0;font:14px system-ui,sans-serif}img{max-width:100%}[hidden]{display:none!important}</style>'
		. '</head><body>';
	$up = gpaj_dir() . $file;
	readfile( file_exists( $up ) ? $up : __DIR__ . '/' . $file );
	echo '</body></html>';
	exit;
}, 0 );

/* ---------- API des scores ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route( 'gpa-jeu/v1', '/scores', array(
		array( 'methods' => 'GET', 'callback' => 'gpaj_get', 'permission_callback' => '__return_true' ),
		array( 'methods' => 'POST', 'callback' => 'gpaj_post', 'permission_callback' => '__return_true' ),
	) );
} );

function gpaj_list( $key ) { $l = get_option( $key, array() ); return is_array( $l ) ? $l : array(); }
function gpaj_sort( $l ) {
	usort( $l, function ( $a, $b ) { return $b['score'] <=> $a['score'] ?: $a['t'] <=> $b['t']; } );
	return array_slice( $l, 0, GPAJ_KEEP );
}
function gpaj_public( $l ) {
	return array_map( function ( $r ) {
		return array( 'id' => $r['id'], 'name' => $r['name'], 'score' => (int) $r['score'], 'wave' => (int) $r['wave'] );
	}, array_slice( $l, 0, 10 ) );
}

function gpaj_get( WP_REST_Request $req ) {
	$game = gpaj_game( $req->get_param( 'game' ) );
	$key  = $req->get_param( 'period' ) === 'all' ? gpaj_key_all( $game ) : gpaj_key_day( $game );
	$res = rest_ensure_response( array( 'scores' => gpaj_public( gpaj_list( $key ) ) ) );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

function gpaj_bad_name( $n ) {
	$bad = array( 'pute', 'salope', 'connard', 'connasse', 'encule', 'enculé', 'batard', 'bâtard', 'nique', 'fdp', 'ntm', 'pd', 'merde', 'bite', 'couille', 'zizi', 'nazi', 'hitler', 'fuck', 'shit', 'bitch', 'dick' );
	$low = mb_strtolower( remove_accents( $n ) );
	$flat = preg_replace( '/[^a-z0-9]/', '', strtr( $low, array( '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '@' => 'a', '$' => 's' ) ) );
	foreach ( $bad as $w ) {
		$w = remove_accents( $w );
		if ( strlen( $w ) <= 3 ) {
			if ( preg_match( '/\b' . preg_quote( $w, '/' ) . '\b/', $low ) ) { return true; }
		} elseif ( strpos( $flat, $w ) !== false ) { return true; }
	}
	return false;
}

function gpaj_post( WP_REST_Request $req ) {
	$p     = $req->get_json_params();
	$name  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) ( $p['name'] ?? '' ) ) ) );
	$name  = mb_substr( $name, 0, 14 );
	$score = (int) ( $p['score'] ?? 0 );
	$wave  = (int) ( $p['wave'] ?? 0 );
	$dur   = (int) ( $p['dur'] ?? 0 );
	$game  = gpaj_game( $p['game'] ?? 'arena' );

	if ( mb_strlen( $name ) < 2 ) { return new WP_Error( 'gpaj_name', 'Écris au moins 2 lettres.', array( 'status' => 400 ) ); }
	if ( gpaj_bad_name( $name ) ) { return new WP_Error( 'gpaj_name', 'Choisis un autre nom.', array( 'status' => 400 ) ); }
	switch ( $game ) {
		case 'arena':  $ok = $score <= $dur * 650 + $wave * $wave * 60 + 1000 && $wave <= intdiv( $dur, 3 ) + 2; break;
		case 'pilot':  $ok = $score <= $dur * 600 + $wave * $wave * 100 + 2000 && $wave <= intdiv( $dur, 4 ) + 2; break;
		case 'memory': $ok = $score <= $wave * $wave * $wave * 4 + $wave * 80 + 1000 && $wave <= intdiv( $dur, 2 ) + 2; break;
		case 'color':  $ok = $score <= $dur * 400 + 1000 && $wave <= intdiv( $dur, 3 ) + 2; break;
		default:       $ok = $score <= $dur * 550 + 2000 && $wave <= intdiv( $dur, 2 ) + 2;
	}
	if ( $score < 1 || $wave < 1 || $dur < 5 || $wave > 200 || ! $ok ) {
		return new WP_Error( 'gpaj_score', 'Score refusé.', array( 'status' => 400 ) );
	}

	$ip = hash( 'sha256', ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt( 'nonce' ) );
	$rl = 'gpaj_rl_' . substr( $ip, 0, 20 );
	if ( get_transient( $rl ) ) { return new WP_Error( 'gpaj_wait', 'Attends quelques secondes et réessaie.', array( 'status' => 429 ) ); }
	set_transient( $rl, 1, 8 );

	$row = array( 'id' => wp_generate_password( 10, false ), 'name' => $name, 'score' => $score, 'wave' => $wave, 'dur' => $dur, 't' => time() );

	$day_key = gpaj_key_day( $game );
	$day     = gpaj_sort( array_merge( gpaj_list( $day_key ), array( $row ) ) );
	update_option( $day_key, $day, false );
	$all = gpaj_sort( array_merge( gpaj_list( gpaj_key_all( $game ) ), array( $row ) ) );
	update_option( gpaj_key_all( $game ), $all, false );

	$rank = 1;
	foreach ( $day as $r ) { if ( $r['score'] > $score ) { $rank++; } }
	return rest_ensure_response( array( 'id' => $row['id'], 'rank_day' => $rank ) );
}

/* Ménage : on garde les classements du jour 14 jours */
add_action( 'gpaj_cleanup', function () {
	global $wpdb;
	$limit = ( new DateTime( '-14 days', gpaj_tz() ) )->format( 'Y-m-d' );
	$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'gpa\_jeu\_day\_%'" );
	foreach ( $names as $n ) { if ( substr( $n, 12, 10 ) < $limit ) { delete_option( $n ); } }
} );
register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'gpaj_cleanup' ) ) { wp_schedule_event( time() + 3600, 'daily', 'gpaj_cleanup' ); }
} );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'gpaj_cleanup' ); } );


/* ---------- Mise à jour automatique depuis GitHub ---------- */
define( 'GPAJ_RAW', 'https://raw.githubusercontent.com/fabricelau/goplay-arena-jeu/' );
function gpaj_dir() { $u = wp_upload_dir( null, false ); return trailingslashit( $u['basedir'] ) . 'gpa-jeu/'; }

function gpaj_sync( $force = false, $ref = 'main' ) {
	/* un commit précis (webhook) n'est jamais mis en cache : la mise à jour est immédiate */
	if ( ! preg_match( '/^[0-9a-f]{40}$/', $ref ) ) { $ref = 'main'; }
	$base = GPAJ_RAW . $ref . '/games/';
	$bust = '?t=' . time();
	$r = wp_remote_get( $base . 'manifest.json' . $bust, array( 'timeout' => 15 ) );
	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) { return 'Impossible de lire GitHub.'; }
	$m = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $m ) || empty( $m['version'] ) || empty( $m['files'] ) || ! is_array( $m['files'] ) ) { return 'Manifeste GitHub invalide.'; }
	$cur = (string) get_option( 'gpaj_remote_ver' );
	if ( $cur && strcmp( (string) $m['version'], $cur ) < 0 ) { return 'Version plus ancienne ignorée (' . esc_html( $m['version'] ) . ').'; }
	if ( ! $force && $cur === $m['version'] ) { return 'Déjà à jour (version ' . esc_html( $m['version'] ) . ').'; }

	/* nouveaux jeux déclarés dans le manifeste */
	$extra = array();
	if ( ! empty( $m['games'] ) && is_array( $m['games'] ) ) {
		foreach ( $m['games'] as $k => $g ) {
			if ( preg_match( '/^[a-z]{3,12}$/', $k ) && ! empty( $g['file'] ) && preg_match( '/^[a-z0-9-]{1,30}\.html$/', $g['file'] ) ) {
				$extra[ $k ] = array( 'file' => $g['file'], 'name' => sanitize_text_field( $g['name'] ?? $k ) );
			}
		}
	}
	$allowed = array_values( array_merge( gpaj_base_games(), wp_list_pluck( $extra, 'file' ) ) );

	$dir = gpaj_dir();
	if ( ! wp_mkdir_p( $dir ) ) { return 'Dossier d\'envoi non accessible en écriture.'; }
	$got = array();
	foreach ( $m['files'] as $file => $sha ) {
		if ( ! in_array( $file, $allowed, true ) ) { continue; }
		$f = wp_remote_get( $base . rawurlencode( $file ) . $bust, array( 'timeout' => 20 ) );
		if ( is_wp_error( $f ) || wp_remote_retrieve_response_code( $f ) !== 200 ) { return 'Téléchargement impossible : ' . esc_html( $file ); }
		$body = wp_remote_retrieve_body( $f );
		if ( ! hash_equals( strtolower( (string) $sha ), hash( 'sha256', $body ) ) ) { return 'Fichier incomplet (empreinte différente) : ' . esc_html( $file ) . '. Réessaie dans 5 minutes.'; }
		$got[ $file ] = $body;
	}
	foreach ( $got as $file => $body ) {
		$tmp = $dir . $file . '.tmp';
		if ( file_put_contents( $tmp, $body ) === false || ! rename( $tmp, $dir . $file ) ) { return 'Écriture impossible : ' . esc_html( $file ); }
	}
	/* mise à jour de l'extension elle-même (plugin/goplay-arena-jeu.php dans le dépôt) */
	$plug = '';
	if ( ! empty( $m['plugin']['version'] ) && ! empty( $m['plugin']['sha'] ) && version_compare( $m['plugin']['version'], GPAJ_VER, '>' ) ) {
		$pr = wp_remote_get( str_replace( '/games/', '/plugin/', $base ) . 'goplay-arena-jeu.php' . $bust, array( 'timeout' => 20 ) );
		$code = ( ! is_wp_error( $pr ) && wp_remote_retrieve_response_code( $pr ) === 200 ) ? wp_remote_retrieve_body( $pr ) : '';
		if ( $code && hash_equals( strtolower( $m['plugin']['sha'] ), hash( 'sha256', $code ) ) && strpos( $code, '<?php' ) === 0 && strpos( $code, 'Plugin Name: GoPlay Arena' ) !== false ) {
			$tmp = __DIR__ . '/goplay-arena-jeu.php.new';
			if ( file_put_contents( $tmp, $code ) !== false && rename( $tmp, __FILE__ ) ) { $plug = ' Extension mise à jour en ' . esc_html( $m['plugin']['version'] ) . '.'; }
		}
	}
	update_option( 'gpaj_extra_games', $extra, false );
	update_option( 'gpaj_remote_ver', $m['version'], false );
	update_option( 'gpaj_synced_at', time(), false );
	return 'Jeux mis à jour : version ' . esc_html( $m['version'] ) . ' (' . count( $got ) . ' fichiers).' . $plug;
}

/* Signal GitHub : à chaque envoi, GitHub appelle /wp-json/gpa-jeu/v1/sync.
   Le site ne fait confiance à rien dans l'appel : il demande lui-même à GitHub le commit annoncé (jamais plus ancien que la version en ligne)
   puis télécharge ce commit précis (pas de cache) en vérifiant les empreintes. */
function gpaj_latest_sha() {
	$r = wp_remote_get( 'https://api.github.com/repos/fabricelau/goplay-arena-jeu/commits/main', array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'goplay-arena-jeu' ) ) );
	if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) { return 'main'; }
	$j = json_decode( wp_remote_retrieve_body( $r ), true );
	return ( is_array( $j ) && ! empty( $j['sha'] ) ) ? $j['sha'] : 'main';
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'gpa-jeu/v1', '/sync', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => function ( WP_REST_Request $req ) {
		if ( get_transient( 'gpaj_hook_wait' ) ) { return array( 'ok' => true, 'msg' => 'Déjà en cours' ); }
		set_transient( 'gpaj_hook_wait', 1, 10 );
		/* le commit annoncé est lu dans le dépôt fabricelau : un commit plus ancien est refusé par gpaj_sync */
		$p   = json_decode( $req->get_body(), true );
		$sha = ( is_array( $p ) && isset( $p['after'] ) && preg_match( '/^[0-9a-f]{40}$/', $p['after'] ) ) ? $p['after'] : gpaj_latest_sha();
		$msg = gpaj_sync( false, $sha );
		wp_schedule_single_event( time() + 60, 'gpaj_sync_event' );
		update_option( 'gpaj_sync_msg', $msg . ' (signal GitHub)', false );
		return array( 'ok' => true, 'msg' => $msg );
	} ) );
} );
add_filter( 'cron_schedules', function ( $s ) { $s['gpaj_15min'] = array( 'interval' => 900, 'display' => 'Toutes les 15 minutes' ); return $s; } );
add_action( 'gpaj_sync_event', function () { update_option( 'gpaj_sync_msg', gpaj_sync( false, gpaj_latest_sha() ), false ); } );
add_action( 'init', function () { if ( ! wp_next_scheduled( 'gpaj_sync_event' ) ) { wp_schedule_event( time() + 60, 'gpaj_15min', 'gpaj_sync_event' ); } } );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'gpaj_sync_event' ); } );
add_action( 'admin_post_gpaj_sync', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé' ); }
	check_admin_referer( 'gpaj_sync' );
	update_option( 'gpaj_sync_msg', gpaj_sync( true, gpaj_latest_sha() ), false );
	wp_safe_redirect( admin_url( 'tools.php?page=gpa-jeu&synced=1' ) );
	exit;
} );

/* ---------- Administration : Outils › Arena Defender ---------- */
add_action( 'admin_menu', function () {
	add_management_page( 'Mini-jeux', 'Mini-jeux GoPlay', 'manage_options', 'gpa-jeu', 'gpaj_admin' );
} );

add_action( 'admin_post_gpaj_del', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Accès refusé' ); }
	check_admin_referer( 'gpaj_del' );
	$id = sanitize_text_field( $_POST['id'] ?? '' );
	$game = gpaj_game( $_POST['game'] ?? 'arena' );
	$reset = sanitize_key( $_POST['reset'] ?? '' );
	if ( $reset === 'day' ) { delete_option( gpaj_key_day( $game ) ); }
	elseif ( $reset === 'all' ) { delete_option( gpaj_key_all( $game ) ); }
	elseif ( $id ) {
		foreach ( array( gpaj_key_day( $game ), gpaj_key_all( $game ) ) as $k ) {
			$l = array_values( array_filter( gpaj_list( $k ), function ( $r ) use ( $id ) { return $r['id'] !== $id; } ) );
			update_option( $k, $l, false );
		}
	}
	wp_safe_redirect( admin_url( 'tools.php?page=gpa-jeu&done=1&game=' . $game ) );
	exit;
} );

function gpaj_admin_table( $title, $list, $reset, $game ) {
	$hid = '<input type="hidden" name="game" value="' . esc_attr( $game ) . '">';
	echo '<h2>' . esc_html( $title ) . ' <small>(' . count( $list ) . ')</small></h2>';
	echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>#</th><th>Nom</th><th>Score</th><th>Vague</th><th>Durée</th><th>Heure</th><th></th></tr></thead><tbody>';
	if ( ! $list ) { echo '<tr><td colspan="7">Aucun score.</td></tr>'; }
	foreach ( array_slice( $list, 0, 30 ) as $i => $r ) {
		$h = ( new DateTime( '@' . $r['t'] ) )->setTimezone( gpaj_tz() )->format( 'd/m H:i' );
		echo '<tr><td>' . ( $i + 1 ) . '</td><td><strong>' . esc_html( $r['name'] ) . '</strong></td><td>' . number_format_i18n( $r['score'] ) . '</td><td>' . (int) $r['wave'] . '</td><td>' . (int) ( $r['dur'] ?? 0 ) . ' s</td><td>' . esc_html( $h ) . '</td><td>'
			. '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0">' . wp_nonce_field( 'gpaj_del', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="gpaj_del"><input type="hidden" name="id" value="' . esc_attr( $r['id'] ) . '">' . $hid . '<button class="button-link-delete button-link">Supprimer</button></form></td></tr>';
	}
	echo '</tbody></table><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:8px 0 24px" onsubmit="return confirm(\'Tout effacer ?\')">' . wp_nonce_field( 'gpaj_del', '_wpnonce', true, false )
		. '<input type="hidden" name="action" value="gpaj_del"><input type="hidden" name="reset" value="' . esc_attr( $reset ) . '">' . $hid . '<button class="button">Remettre à zéro</button></form>';
}

function gpaj_admin() {
	$names = gpaj_names();
	$paths = gpaj_paths();
	$game  = gpaj_game( $_GET['game'] ?? 'arena' );
	echo '<div class="wrap"><h1>Mini-jeux GoPlay Arena</h1>';
	if ( isset( $_GET['done'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>Classement mis à jour.</p></div>'; }
	$ver = get_option( 'gpaj_remote_ver' ); $at = (int) get_option( 'gpaj_synced_at' );
	echo '<div class="card" style="max-width:720px"><h2>Mise à jour automatique</h2><p>Les jeux se mettent à jour tout seuls depuis GitHub (fabricelau/goplay-arena-jeu) dès que Claude y envoie une modification (signal GitHub), et par sécurité toutes les 15 minutes.</p>';
	echo '<p><strong>Version en ligne :</strong> ' . ( $ver ? esc_html( $ver ) : 'jeux d\'origine de l\'extension' ) . ( $at ? ' · dernière mise à jour le ' . esc_html( ( new DateTime( '@' . $at ) )->setTimezone( gpaj_tz() )->format( 'd/m/Y à H:i' ) ) : '' ) . '</p>';
	if ( $msg = get_option( 'gpaj_sync_msg' ) ) { echo '<p><em>Dernière vérification : ' . esc_html( $msg ) . '</em></p>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . wp_nonce_field( 'gpaj_sync', '_wpnonce', true, false ) . '<input type="hidden" name="action" value="gpaj_sync"><button class="button button-primary">Mettre à jour les jeux maintenant</button></form></div>';
	echo '<h2 class="nav-tab-wrapper">';
	foreach ( $names as $k => $n ) {
		echo '<a class="nav-tab' . ( $k === $game ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'tools.php?page=gpa-jeu&game=' . $k ) ) . '">' . esc_html( $n ) . '</a>';
	}
	echo '</h2>';
	$url = home_url( $paths[ $game ] );
	echo '<p>Adresse du jeu (QR code) : <a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>. Supprime ici un nom déplacé ou un score suspect.</p>';
	gpaj_admin_table( 'Aujourd\'hui (' . ( new DateTime( 'now', gpaj_tz() ) )->format( 'd/m/Y' ) . ')', gpaj_list( gpaj_key_day( $game ) ), 'day', $game );
	gpaj_admin_table( 'Tout temps', gpaj_list( gpaj_key_all( $game ) ), 'all', $game );
	echo '</div>';
}
