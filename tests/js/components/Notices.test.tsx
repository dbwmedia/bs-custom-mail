/**
 * Notices component tests
 */
import { render, screen, fireEvent } from '@testing-library/react';
import { Notices } from '../../../src/admin/components/Notices';

describe('Notices', () => {
	it('should render nothing when no notices', () => {
		const { container } = render(<Notices notices={[]} onRemove={jest.fn()} />);
		expect(container.firstChild).toBeNull();
	});

	it('should render notices', () => {
		const notices = [
			{ id: '1', status: 'success' as const, message: 'Success message' },
			{ id: '2', status: 'error' as const, message: 'Error message' },
		];

		render(<Notices notices={notices} onRemove={jest.fn()} />);

		expect(screen.getByText('Success message')).toBeInTheDocument();
		expect(screen.getByText('Error message')).toBeInTheDocument();
	});

	it('should call onRemove when dismissed', () => {
		const onRemove = jest.fn();
		const notices = [
			{ id: '1', status: 'success' as const, message: 'Test message' },
		];

		render(<Notices notices={notices} onRemove={onRemove} />);

		const dismissButton = screen.getByText('Dismiss');
		fireEvent.click(dismissButton);

		expect(onRemove).toHaveBeenCalledWith('1');
	});
});
