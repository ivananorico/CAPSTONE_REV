import React, { useState, useRef, useEffect } from "react";
import { useParams, useNavigate } from "react-router-dom";
import "./MapEditor.css";

export default function MapEditor() {
  const { id } = useParams();
  const navigate = useNavigate();
  
  const [mapData, setMapData] = useState(null);
  const [stalls, setStalls] = useState([]);
  const [stallCount, setStallCount] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [modalOpen, setModalOpen] = useState(false);
  const [selectedStallIndex, setSelectedStallIndex] = useState(null);
  const [modalPos, setModalPos] = useState({ x: 0, y: 0 });

  const marketMapRef = useRef(null);
  const modalRef = useRef(null);
  const API_BASE = "http://localhost/revenue/backend/Market/MarketCreator";

  // Fetch map and stalls data
  useEffect(() => {
    fetchMapData();
  }, [id]);

  const fetchMapData = async () => {
    try {
      const res = await fetch(`${API_BASE}/map_display.php?map_id=${id}`);
      if (!res.ok) throw new Error(`Network error: ${res.status}`);
      
      const data = await res.json();
      if (data.status === "success") {
        setMapData(data.map);
        setStalls(data.stalls || []);
        setStallCount(data.stalls?.length || 0);
      } else {
        throw new Error(data.message || "Failed to fetch map data");
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  // Add a new stall
  const addStall = () => {
    const newCount = stallCount + 1;
    setStallCount(newCount);
    setStalls([
      ...stalls,
      { 
        name: `Stall ${newCount}`, 
        pos_x: 50, 
        pos_y: 50, 
        status: "available", 
        price: 0, 
        height: 0, 
        length: 0, 
        width: 0,
        isNew: true
      }
    ]);
  };

 // Delete a stall
const deleteStall = async (index) => {
  const stall = stalls[index];
  if (window.confirm(`Delete ${stall.name}?`)) {
    // If stall has an ID (exists in database), delete from backend
    if (stall.id && !stall.isNew) {
      try {
        console.log("Deleting stall ID:", stall.id); // Debug log
        
        const formData = new FormData();
        formData.append("stall_id", stall.id);

        const res = await fetch(`${API_BASE}/delete_stall.php`, {
          method: "POST",
          body: formData
        });
        
        console.log("Delete response status:", res.status); // Debug log
        
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const data = await res.json();
        console.log("Delete response data:", data); // Debug log
        
        if (data.status !== "success") {
          throw new Error(data.message || "Failed to delete stall");
        }
        
        console.log("Stall deleted successfully"); // Debug log
      } catch (err) {
        console.error("Delete error details:", err); // Debug log
        alert("Delete failed: " + err.message);
        return;
      }
    }
    
    // Remove from local state
    const updated = stalls.filter((_, i) => i !== index);
    setStalls(updated);
    setStallCount(updated.length);
  }
};

  // Save updates to backend
  const saveUpdates = async () => {
    try {
      const res = await fetch(`${API_BASE}/update_map.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          map_id: id,
          stalls: stalls
        })
      });
      
      const data = await res.json();
      if (data.status === "success") {
        alert("Map updated successfully!");
        fetchMapData();
      } else {
        throw new Error(data.message || "Failed to update map");
      }
    } catch (err) {
      alert("Update failed: " + err.message);
    }
  };

  // Drag stalls
  const handleDrag = (e, index) => {
    const containerRect = marketMapRef.current.getBoundingClientRect();
    const x = e.clientX - containerRect.left - 31.5;
    const y = e.clientY - containerRect.top - 29;

    const updated = [...stalls];
    updated[index].pos_x = Math.max(0, Math.min(containerRect.width - 63, x));
    updated[index].pos_y = Math.max(0, Math.min(containerRect.height - 58, y));
    setStalls(updated);
  };

  const handleMouseDown = (e, index) => {
    e.preventDefault();
    const onMouseMove = (ev) => handleDrag(ev, index);
    const onMouseUp = () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    };
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  };

  // Open stall modal for editing
  const openEditModal = (index, e) => {
    e.preventDefault();
    setSelectedStallIndex(index);
    const viewportX = e.clientX;
    const viewportY = e.clientY;
    const modalWidth = 300;
    const modalHeight = 400;

    let x = viewportX;
    let y = viewportY;
    if (x + modalWidth > window.innerWidth) x = window.innerWidth - modalWidth - 10;
    if (y + modalHeight > window.innerHeight) y = window.innerHeight - modalHeight - 10;
    x = Math.max(10, x);
    y = Math.max(10, y);
    setModalPos({ x, y });
    setModalOpen(true);
  };

  const handleBackdropClick = (e) => {
    if (modalRef.current && !modalRef.current.contains(e.target)) {
      setModalOpen(false);
    }
  };

  if (loading) return (
    <div className="map-editor-container">
      <h1>Loading Map...</h1>
      <p>Please wait while we load the map data.</p>
    </div>
  );

  if (error) return (
    <div className="map-editor-container">
      <h1>Error</h1>
      <p className="error-message">{error}</p>
      <button 
        onClick={() => navigate("/Market/ViewAllMaps")}
        className="back-button"
      >
        Back to Maps
      </button>
    </div>
  );

  return (
    <div className="map-editor-container">
      <div className="header-section">
        <h1>Edit Map: {mapData?.name}</h1>
        <div className="header-buttons">
          <button 
            onClick={() => navigate("/Market/ViewAllMaps")}
            className="btn-secondary"
          >
            Back to Maps
          </button>
          <button 
            onClick={() => navigate(`/Market/MarketOutput/view/${id}`)}
            className="btn-primary"
          >
            View as Customer
          </button>
        </div>
      </div>

      <div className="instructions">
        <p>
          <strong>Instructions:</strong> Drag stalls to reposition. Right-click to edit details. 
          Click the × button to delete stalls. Add new stalls with the button below.
        </p>
      </div>

      <div
        ref={marketMapRef}
        className="market-map"
        style={{ 
          backgroundImage: mapData ? `url(http://localhost/revenue/${mapData.image_path})` : "none"
        }}
      >
        {stalls.map((stall, index) => (
          <div
            key={stall.id || `new-${index}`}
            className={`stall ${stall.status}`}
            style={{ left: stall.pos_x, top: stall.pos_y }}
            onMouseDown={(e) => handleMouseDown(e, index)}
            onContextMenu={(e) => openEditModal(index, e)}
          >
            <div className="stall-content">
              <div className="stall-name">{stall.name}</div>
              <div className="stall-price">{stall.price > 0 ? `₱${stall.price}` : ""}</div>
              <div className="stall-size">{stall.length}m × {stall.width}m × {stall.height}m</div>
            </div>

            <button
              className="delete-stall-btn"
              onClick={(e) => { e.stopPropagation(); deleteStall(index); }}
              title="Delete stall"
            >
              ×
            </button>
          </div>
        ))}
      </div>

      <div className="controls">
        <button onClick={addStall} className="btn-add">Add New Stall</button>
        <button onClick={saveUpdates} className="btn-save">Save Changes</button>
      </div>

      {modalOpen && (
        <div className="modal-backdrop" onClick={handleBackdropClick}>
          <div
            ref={modalRef}
            className="price-modal"
            style={{ left: `${modalPos.x}px`, top: `${modalPos.y}px` }}
            onClick={(e) => e.stopPropagation()}
          >
            <h4>Edit Stall Details</h4>

            <label>Price (₱)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.price || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].price = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Height (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.height || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].height = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Length (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.length || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].length = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <label>Width (m)</label>
            <input
              type="number"
              value={stalls[selectedStallIndex]?.width || 0}
              onChange={(e) => {
                const updated = [...stalls];
                updated[selectedStallIndex].width = parseFloat(e.target.value) || 0;
                setStalls(updated);
              }}
              step="0.01"
            />

            <div className="modal-buttons">
              <button onClick={() => setModalOpen(false)}>Save</button>
              <button onClick={() => setModalOpen(false)}>Cancel</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}