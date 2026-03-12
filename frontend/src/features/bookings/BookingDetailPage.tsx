import React from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import BookingDetailPanel from './BookingDetailPanel'

/**
 * Wrapper page that renders the BookingDetailPanel as a route.
 * Mounts at `/my-bookings/:id` or `/admin/bookings/:id`.
 */
const BookingDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const handleClose = () => {
    // Navigate back to the parent list.
    // We can infer context from the current URL if needed, but a simple '..' works
    // if mounted cleanly, or we can just go back to the list:
    if (window.location.pathname.startsWith('/admin')) {
      navigate('/admin/bookings')
    } else {
      navigate('/my-bookings')
    }
  }

  return (
    <div className="relative min-h-screen bg-gray-50">
      {/* Background content placeholder - could optionally render the list underneath 
          if we used nested routes, but as a standalone route we just show a gray bg 
          or redirect if closed */}

      <BookingDetailPanel
        bookingId={id ? parseInt(id, 10) : null}
        open={true}
        onClose={handleClose}
      />
    </div>
  )
}

export default BookingDetailPage
