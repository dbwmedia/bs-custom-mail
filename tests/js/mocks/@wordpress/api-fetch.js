/**
 * Mock for @wordpress/api-fetch
 */
const mockApiFetch = jest.fn();

module.exports = mockApiFetch;
module.exports.__esModule = true;
module.exports.default = mockApiFetch;
module.exports.__mockApiFetch = mockApiFetch;

// Helper to reset mock
module.exports.resetApiFetchMock = () => {
	mockApiFetch.mockReset();
};

// Helper to set successful response
module.exports.mockApiFetchSuccess = (data) => {
	mockApiFetch.mockResolvedValue(data);
};

// Helper to set error response
module.exports.mockApiFetchError = (error) => {
	mockApiFetch.mockRejectedValue(error);
};
