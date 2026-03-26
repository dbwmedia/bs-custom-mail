/**
 * Settings view tests
 */
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { Settings } from '../../../src/admin/views/Settings';
import apiFetch from '@wordpress/api-fetch';

jest.mock('@wordpress/api-fetch');

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

describe('Settings', () => {
	const mockSettings = {
		trigger_status: 'processing',
		from_name: 'Bootsschule',
		from_email: 'info@bootsschule.de',
	};

	beforeEach(() => {
		jest.clearAllMocks();
		mockedApiFetch.mockResolvedValue(mockSettings);
	});

	it('should render loading state initially', () => {
		render(<Settings />);
		expect(screen.getByText('Lade Einstellungen...')).toBeInTheDocument();
	});

	it('should render settings after loading', async () => {
		render(<Settings />);

		await waitFor(() => {
			expect(screen.getByText('Einstellungen')).toBeInTheDocument();
		});

		// Check for inputs by their values since labels are not properly associated in mocks
		expect(screen.getByDisplayValue('Bootsschule')).toBeInTheDocument();
		expect(screen.getByDisplayValue('info@bootsschule.de')).toBeInTheDocument();
	});

	it('should save settings', async () => {
		mockedApiFetch
			.mockResolvedValueOnce(mockSettings)
			.mockResolvedValueOnce({ ...mockSettings, from_name: 'New Name' });

		render(<Settings />);

		await waitFor(() => {
			expect(screen.getByDisplayValue('Bootsschule')).toBeInTheDocument();
		});

		const nameInput = screen.getAllByDisplayValue('Bootsschule')[0];
		fireEvent.change(nameInput, { target: { value: 'New Name' } });

		const saveButton = screen.getByText('Einstellungen speichern');
		fireEvent.click(saveButton);

		await waitFor(() => {
			// Component sends all settings, not just changed ones
			expect(apiFetch).toHaveBeenCalledWith(
				expect.objectContaining({
					path: '/bs-custom-mail/v1/settings',
					method: 'PUT',
					data: expect.objectContaining({ from_name: 'New Name' }),
				})
			);
		});

		// Check for success message
		expect(screen.getByText('Einstellungen gespeichert.')).toBeInTheDocument();
	});
});
