/**
 * Settings view tests
 */
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { Settings } from '../../../src/admin/views/Settings';
import { useSettings, useTemplates } from '../../../src/admin/hooks';

// Mock the hooks
jest.mock('../../../src/admin/hooks', () => ({
	useSettings: jest.fn(),
	useTemplates: jest.fn(),
}));

const mockedUseSettings = useSettings as jest.MockedFunction<typeof useSettings>;
const mockedUseTemplates = useTemplates as jest.MockedFunction<typeof useTemplates>;

describe('Settings', () => {
	const mockSettings = {
		trigger_status: 'processing',
		from_name: 'Bootsschule',
		from_email: 'info@bootsschule.de',
	};

	beforeEach(() => {
		jest.clearAllMocks();
		// Mock useTemplates for all tests
		mockedUseTemplates.mockReturnValue({
			templates: [],
			isLoading: false,
			createTemplate: jest.fn(),
			updateTemplate: jest.fn(),
			deleteTemplate: jest.fn(),
			sendTestEmail: jest.fn(),
			error: null,
		});
	});

	it('should render loading state initially', () => {
		mockedUseSettings.mockReturnValue({
			settings: null,
			isLoading: true,
			updateSettings: jest.fn(),
			error: null,
		});

		render(<Settings />);
		expect(screen.getByText('Lade Einstellungen...')).toBeInTheDocument();
	});

	it('should render settings after loading', async () => {
		mockedUseSettings.mockReturnValue({
			settings: mockSettings,
			isLoading: false,
			updateSettings: jest.fn(),
			error: null,
		});

		render(<Settings />);

		await waitFor(() => {
			expect(screen.getByText('Einstellungen')).toBeInTheDocument();
		});

		// Check for inputs by their values
		expect(screen.getByDisplayValue('Bootsschule')).toBeInTheDocument();
		expect(screen.getByDisplayValue('info@bootsschule.de')).toBeInTheDocument();
	});

	it('should save settings', async () => {
		const mockUpdateSettings = jest.fn().mockResolvedValue(undefined);
		mockedUseSettings.mockReturnValue({
			settings: mockSettings,
			isLoading: false,
			updateSettings: mockUpdateSettings,
			error: null,
		});

		render(<Settings />);

		await waitFor(() => {
			expect(screen.getByDisplayValue('Bootsschule')).toBeInTheDocument();
		});

		const nameInput = screen.getByDisplayValue('Bootsschule');
		fireEvent.change(nameInput, { target: { value: 'New Name' } });

		const saveButton = screen.getByText('Einstellungen speichern');
		fireEvent.click(saveButton);

		await waitFor(() => {
			expect(mockUpdateSettings).toHaveBeenCalledWith(
				expect.objectContaining({ from_name: 'New Name' })
			);
		});

		// Check for success message
		await waitFor(() => {
			expect(screen.getByText('Einstellungen gespeichert.')).toBeInTheDocument();
		});
	});
});
