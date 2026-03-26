/**
 * useSettings hook tests
 */
import { renderHook, waitFor } from '@testing-library/react';
import { useSettings } from '../../../src/admin/hooks/useSettings';
import apiFetch from '@wordpress/api-fetch';

jest.mock('@wordpress/api-fetch');

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

describe('useSettings', () => {
	const mockSettings = {
		trigger_status: 'processing',
		from_name: 'Bootsschule',
		from_email: 'info@bootsschule.de',
	};

	beforeEach(() => {
		jest.clearAllMocks();
	});

	it('should fetch settings on mount', async () => {
		mockedApiFetch.mockResolvedValue(mockSettings);

		const { result } = renderHook(() => useSettings());

		expect(result.current.isLoading).toBe(true);

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		expect(result.current.settings).toEqual(mockSettings);
	});

	it('should update settings', async () => {
		const updatedSettings = { ...mockSettings, from_name: 'New Name' };
		mockedApiFetch
			.mockResolvedValueOnce(mockSettings)
			.mockResolvedValueOnce(updatedSettings);

		const { result } = renderHook(() => useSettings());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		await result.current.updateSettings({ from_name: 'New Name' });

		expect(apiFetch).toHaveBeenCalledWith({
			path: '/bs-custom-mail/v1/settings',
			method: 'PUT',
			data: { from_name: 'New Name' },
		});

		// State update is async, wait for it
		await waitFor(() => {
			expect(result.current.settings?.from_name).toBe('New Name');
		});
	});

	it('should handle fetch error', async () => {
		mockedApiFetch.mockRejectedValue(new Error('Failed to load'));

		const { result } = renderHook(() => useSettings());

		await waitFor(() => {
			expect(result.current.isLoading).toBe(false);
		});

		expect(result.current.error).toBe('Failed to load');
	});
});
