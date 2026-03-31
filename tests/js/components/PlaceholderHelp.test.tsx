/**
 * PlaceholderHelp component tests
 */
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { PlaceholderHelp } from '../../../src/admin/components/PlaceholderHelp';
import { usePlaceholders } from '../../../src/admin/hooks';

// Mock the hooks
jest.mock('../../../src/admin/hooks', () => ({
	usePlaceholders: jest.fn(),
}));

const mockedUsePlaceholders = usePlaceholders as jest.MockedFunction<typeof usePlaceholders>;

// Mock clipboard API
Object.assign(navigator, {
	clipboard: {
		writeText: jest.fn(),
	},
});

describe('PlaceholderHelp', () => {
	const mockPlaceholders = [
		{ code: '{{customer_name}}', label: 'Kundenname', description: 'Vorname des Kunden' },
		{ code: '{{order_number}}', label: 'Bestellnummer', description: 'WooCommerce Bestellnummer' },
		{ code: '{{product_name}}', label: 'Produktname', description: 'Name des Produkts' },
	];

	beforeEach(() => {
		jest.clearAllMocks();
		mockedUsePlaceholders.mockReturnValue({
			placeholders: mockPlaceholders,
			isLoading: false,
		});
	});

	it('should render placeholder list', () => {
		render(<PlaceholderHelp />);

		expect(screen.getByText('{{customer_name}}')).toBeInTheDocument();
		expect(screen.getByText('{{order_number}}')).toBeInTheDocument();
		expect(screen.getByText('{{product_name}}')).toBeInTheDocument();
	});

	it('should copy placeholder to clipboard', async () => {
		render(<PlaceholderHelp />);

		const codeElement = screen.getByText('{{customer_name}}');
		fireEvent.click(codeElement);

		expect(navigator.clipboard.writeText).toHaveBeenCalledWith('{{customer_name}}');

		// Should show "Kopiert!" temporarily
		await waitFor(() => {
			expect(screen.getByText('Kopiert!')).toBeInTheDocument();
		});
	});

	it('should call onCopy callback', () => {
		const onCopy = jest.fn();
		render(<PlaceholderHelp onCopy={onCopy} />);

		const codeElement = screen.getByText('{{order_number}}');
		fireEvent.click(codeElement);

		expect(onCopy).toHaveBeenCalledWith('{{order_number}}');
	});

	it('should render loading state', () => {
		mockedUsePlaceholders.mockReturnValue({
			placeholders: [],
			isLoading: true,
		});

		render(<PlaceholderHelp />);

		expect(screen.getByText('Lade Platzhalter...')).toBeInTheDocument();
	});
});
