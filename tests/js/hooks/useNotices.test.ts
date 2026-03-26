/**
 * useNotices hook tests
 */
import { renderHook, act } from '@testing-library/react';
import { useNotices } from '../../../src/admin/hooks/useNotices';

describe('useNotices', () => {
	beforeEach(() => {
		jest.useFakeTimers();
	});

	afterEach(() => {
		jest.useRealTimers();
	});

	it('should add notice', () => {
		const { result } = renderHook(() => useNotices());

		act(() => {
			result.current.addNotice('success', 'Operation successful');
		});

		expect(result.current.notices).toHaveLength(1);
		expect(result.current.notices[0].status).toBe('success');
		expect(result.current.notices[0].message).toBe('Operation successful');
	});

	it('should remove notice', () => {
		const { result } = renderHook(() => useNotices());

		act(() => {
			result.current.addNotice('success', 'Test message');
		});

		const noticeId = result.current.notices[0].id;

		act(() => {
			result.current.removeNotice(noticeId);
		});

		expect(result.current.notices).toHaveLength(0);
	});

	it('should auto-remove success notices after 5 seconds', () => {
		const { result } = renderHook(() => useNotices());

		act(() => {
			result.current.success('Auto remove test');
		});

		expect(result.current.notices).toHaveLength(1);

		act(() => {
			jest.advanceTimersByTime(5000);
		});

		expect(result.current.notices).toHaveLength(0);
	});

	it('should provide convenience methods', () => {
		const { result } = renderHook(() => useNotices());

		act(() => {
			result.current.success('Success');
			result.current.error('Error');
			result.current.warning('Warning');
			result.current.info('Info');
		});

		expect(result.current.notices).toHaveLength(4);
		expect(result.current.notices[0].status).toBe('success');
		expect(result.current.notices[1].status).toBe('error');
		expect(result.current.notices[2].status).toBe('warning');
		expect(result.current.notices[3].status).toBe('info');
	});
});
