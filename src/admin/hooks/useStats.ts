/**
 * Custom hook for statistics operations
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Stats, Activity } from '../types';

interface UseStatsReturn {
	stats: Stats | null;
	recentActivity: Activity[];
	isLoading: boolean;
	error: string | null;
	refetch: () => void;
}

export function useStats(): UseStatsReturn {
	const [stats, setStats] = useState<Stats | null>( null );
	const [recentActivity, setRecentActivity] = useState<Activity[]>( [] );
	const [isLoading, setIsLoading] = useState( false );
	const [error, setError] = useState<string | null>( null );

	const fetchStats = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const [statsData, activityData] = await Promise.all( [
				apiFetch<Stats>( {
					path: '/bs-custom-mail/v1/stats',
				} ),
				apiFetch<Activity[]>( {
					path: '/bs-custom-mail/v1/stats/recent',
				} ),
			] );
			setStats( statsData );
			setRecentActivity( activityData );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to fetch statistics' );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchStats();
	}, [fetchStats] );

	return {
		stats,
		recentActivity,
		isLoading,
		error,
		refetch: fetchStats,
	};
}
