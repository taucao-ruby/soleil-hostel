/**
 * Reusable skeleton loading card for grid listings (rooms, locations, etc.)
 */
const SkeletonCard: React.FC = () => (
  <div className="overflow-hidden bg-white shadow-md rounded-xl animate-pulse">
    <div className="h-48 bg-gray-200" />
    <div className="p-6">
      <div className="h-6 mb-4 bg-gray-200 rounded" />
      <div className="h-4 mb-2 bg-gray-200 rounded" />
      <div className="w-3/4 h-4 mb-4 bg-gray-200 rounded" />
      <div className="flex items-center justify-between">
        <div className="w-20 h-8 bg-gray-200 rounded" />
        <div className="w-24 h-6 bg-gray-200 rounded" />
      </div>
    </div>
  </div>
)

export default SkeletonCard
