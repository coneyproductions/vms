<?php
/**
 * RFC 5545 iCalendar formatter for normalized feed occurrences.
 */

namespace ConeyProductions\BackstageCalendarFeeds;

final class ICS_Formatter {
	/**
	 * Render a complete iCalendar document.
	 *
	 * Timed occurrences are emitted as UTC instants, preserving provider timezone
	 * and DST semantics without requiring consumers to interpret a custom VTIMEZONE.
	 *
	 * @param array<int,array<string,mixed>> $events        Feed occurrences.
	 * @param string                         $calendar_name Safe calendar name.
	 * @param int|null                       $dtstamp       UTC timestamp for deterministic tests.
	 * @return string
	 */
	public function render( $events, $calendar_name, $dtstamp = null ) {
		$stamp = null === $dtstamp ? time() : (int) $dtstamp;
		$lines = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Coney Productions//Backstage Calendar Feeds 0.1.3//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . self::escape_text( $calendar_name ),
		);

		foreach ( $events as $event ) {
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:' . (string) $event['uid'];
			$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z', $stamp );
			if ( ! empty( $event['all_day'] ) ) {
				$lines[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', (string) $event['start']['value'] );
				$lines[] = 'DTEND;VALUE=DATE:' . str_replace( '-', '', (string) $event['end']['value'] );
			} else {
				$lines[] = 'DTSTART:' . gmdate( 'Ymd\THis\Z', (int) $event['start']['timestamp'] );
				$lines[] = 'DTEND:' . gmdate( 'Ymd\THis\Z', (int) $event['end']['timestamp'] );
			}
			$lines[] = 'SUMMARY:' . self::escape_text( (string) $event['summary'] );
			if ( '' !== (string) $event['location'] ) {
				$lines[] = 'LOCATION:' . self::escape_text( (string) $event['location'] );
			}
			$lines[] = 'TRANSP:' . (string) $event['transparency'];
			$lines[] = 'STATUS:' . (string) $event['status'];
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';

		$folded = array_map( array( self::class, 'fold_line' ), $lines );
		return implode( "\r\n", $folded ) . "\r\n";
	}

	/**
	 * Escape an RFC 5545 TEXT value.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	public static function escape_text( $value ) {
		return str_replace(
			array( '\\', "\r\n", "\r", "\n", ';', ',' ),
			array( '\\\\', '\\n', '\\n', '\\n', '\\;', '\\,' ),
			(string) $value
		);
	}

	/**
	 * Fold a content line at 75 octets without splitting a UTF-8 sequence.
	 *
	 * @param string $line Unfolded line.
	 * @return string
	 */
	public static function fold_line( $line ) {
		$remaining = (string) $line;
		$segments  = array();
		$limit     = 75;
		while ( strlen( $remaining ) > $limit ) {
			$take       = self::utf8_boundary( $remaining, $limit );
			$segments[] = substr( $remaining, 0, $take );
			$remaining  = substr( $remaining, $take );
			$limit      = 74;
		}
		$segments[] = $remaining;
		return implode( "\r\n ", $segments );
	}

	/** @param string $value UTF-8 bytes. @param int $limit Maximum bytes. */
	private static function utf8_boundary( $value, $limit ) {
		$take = min( strlen( $value ), (int) $limit );
		while ( $take > 0 && $take < strlen( $value ) && ( ord( $value[ $take ] ) & 0xC0 ) === 0x80 ) {
			--$take;
		}
		return max( 1, $take );
	}
}
